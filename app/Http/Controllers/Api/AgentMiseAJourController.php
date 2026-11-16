<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AgentMiseAJour;
use App\Models\AgentVersion;
use App\Models\Poste;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Mise à jour de l'Écran (étape 3) : les versions viennent de la table « agent_versions ».
 * Paquets en stockage PRIVÉ : storage/app/private/agent-packages/ (jamais le disque « public »).
 * Pilote d'abord : un poste n'est concerné que par la version pilote (s'il y est inscrit) ou la version générale.
 */
class AgentMiseAJourController extends Controller
{
    private function authentifier(Request $request): ?Poste
    {
        $token = $request->header('X-Agent-Token');
        if (!$token) return null;
        return Poste::where('agent_token', $token)->first();
    }

    public function visee(Request $request): JsonResponse
    {
        $poste = $this->authentifier($request);
        if ($poste === null) return response()->json(['ok' => false, 'message' => 'Token invalide.'], 401);

        $actuelle = (string) $request->query('version', '');
        if ($actuelle !== '' && preg_match('/^[0-9A-Za-z._-]{1,32}$/', $actuelle) && $actuelle !== $poste->agent_version) {
            $poste->update(['agent_version' => $actuelle]);
        }

        $v = AgentVersion::viseePour($poste);
        if ($v === null || !$v->fichierExiste()) {
            return response()->json(['ok' => true, 'version' => null]);
        }

        return response()->json(['ok' => true, 'version' => $v->version, 'sha256' => strtolower($v->sha256)]);
    }

    public function paquet(Request $request, string $version)
    {
        $poste = $this->authentifier($request);
        if ($poste === null) return response()->json(['ok' => false, 'message' => 'Token invalide.'], 401);

        // Un poste ne télécharge que SA version visée
        $v = AgentVersion::viseePour($poste);
        if ($v === null || $v->version !== $version) {
            return response()->json(['ok' => false, 'message' => 'Version non disponible.'], 404);
        }
        if (!$v->fichierExiste()) {
            return response()->json(['ok' => false, 'message' => 'Paquet introuvable.'], 404);
        }

        return response()->download($v->chemin(), $v->fichier);
    }

    public function resultat(Request $request): JsonResponse
    {
        $poste = $this->authentifier($request);
        if ($poste === null) return response()->json(['ok' => false, 'message' => 'Token invalide.'], 401);

        $data = $request->validate([
            'version' => ['required', 'string', 'max:32'],
            'succes'  => ['required', 'boolean'],
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        $succes  = (bool) $data['succes'];
        $message = isset($data['message']) ? mb_substr((string) $data['message'], 0, 500) : null;

        AgentMiseAJour::create([
            'poste_id' => $poste->id,
            'version'  => $data['version'],
            'succes'   => $succes,
            'message'  => $message,
        ]);

        $poste->forceFill([
            'derniere_maj_at'       => now(),
            'derniere_maj_resultat' => $succes ? 'succes' : 'echec',
            'derniere_maj_message'  => $message,
        ])->save();

        if ($succes) {
            $poste->update(['agent_version' => $data['version']]);
            Log::info("Mise à jour OK : poste « {$poste->nom_poste} » en version {$data['version']}.");
        } else {
            Log::warning("Mise à jour ÉCHOUÉE : poste « {$poste->nom_poste} », version {$data['version']} : " . ($message ?? ''));
        }

        return response()->json(['ok' => true]);
    }
}