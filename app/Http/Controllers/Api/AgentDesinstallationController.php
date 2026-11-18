<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AgentMiseAJour;
use App\Models\Poste;
use App\Models\PosteCommande;
use App\Models\PosteMessage;
use App\Services\AccesInternetService;
use App\Services\IdentiteMaterielle;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Désinstallation d'un poste, demandée par le poste lui-même (désinstalleur → Gardien --desinscrire).
 *
 * Garde-fous : jeton du poste, identifiant unique, empreinte matérielle (un PC cloné ne peut pas supprimer
 * le poste d'un autre), refus si une session est en cours.
 * Un poste qui a déjà des sessions n'est JAMAIS détruit (l'historique de facturation est conservé) :
 * il est retiré = renommé « … (retiré) », désactivé, jeton changé, accès Internet coupé.
 */
class AgentDesinstallationController extends Controller
{
    public function desinstaller(Request $request): JsonResponse
    {
        $token = $request->header('X-Agent-Token');
        $poste = $token ? Poste::where('agent_token', $token)->first() : null;
        if ($poste === null) {
            return response()->json([
                'ok'      => false,
                'code'    => 'poste_inconnu',
                'message' => "Ce poste n'est pas (ou plus) enregistré sur le serveur.",
            ], 401);
        }

        $data = $request->validate([
            'identifiant_unique' => ['required', 'string', 'max:64'],
            'empreinte'          => ['nullable', 'array'],
            'empreinte.uuid'     => ['nullable', 'string', 'max:40'],
            'empreinte.macs'     => ['nullable', 'array', 'max:16'],
            'empreinte.macs.*'   => ['string', 'max:17'],
        ]);

        $nom = $poste->nom_poste;

        // Cet ordinateur est-il bien celui du poste ? (clone : refus, l'entrée du serveur n'est pas touchée)
        $emp = IdentiteMaterielle::normaliser($data['empreinte'] ?? null);
        if (!hash_equals((string) $poste->identifiant_unique, $data['identifiant_unique'])
            || IdentiteMaterielle::memeMachine(IdentiteMaterielle::dePoste($poste), $emp) === false) {
            Log::warning("Désinstallation refusée pour « {$nom} » : ce n'est pas l'ordinateur enregistré.");
            return response()->json([
                'ok'      => false,
                'code'    => 'autre_ordinateur',
                'message' => "Cet ordinateur n'est pas celui enregistré pour « {$nom} » : l'entrée du serveur n'a pas été touchée.",
            ], 409);
        }

        if ($poste->sessionActive() !== null) {
            return response()->json([
                'ok'      => false,
                'code'    => 'session_active',
                'message' => "Une session est en cours sur « {$nom} » : terminez-la depuis l'interface web, puis recommencez.",
            ], 409);
        }

        // Plus d'accès Internet pour ce poste (si la coupure n'est pas confirmée, on le journalise seulement)
        try {
            app(AccesInternetService::class)->couper($poste);
        } catch (\Throwable $e) {
            Log::warning("Désinstallation de « {$nom} » : coupure Internet non confirmée ({$e->getMessage()}).");
        }

        // Aucun historique : suppression réelle
        if (!$poste->sessions()->exists()) {
            try {
                DB::transaction(function () use ($poste) {
                    PosteMessage::where('poste_id', $poste->id)->delete();
                    PosteCommande::where('poste_id', $poste->id)->delete();
                    AgentMiseAJour::where('poste_id', $poste->id)->delete();
                    $poste->delete();
                });

                Log::info("Poste « {$nom} » supprimé du serveur (désinstallation depuis le poste).");
                return response()->json(['ok' => true, 'supprime' => true, 'message' => "Poste « {$nom} » supprimé du serveur."]);
            } catch (QueryException $e) {
                // Une autre table s'oppose à la suppression : on retire le poste au lieu de le détruire
                Log::warning("Poste « {$nom} » : suppression impossible ({$e->getCode()}), le poste est retiré à la place.");
            }
        }

        // Historique à conserver : poste retiré
        $nouveauNom = Poste::nomDisponible(Poste::nettoyerNom($nom . ' (retiré)'), $poste->id);

        DB::transaction(function () use ($poste, $nouveauNom) {
            PosteCommande::where('poste_id', $poste->id)->whereIn('statut', ['en_attente', 'recue'])->delete();
            $poste->update([
                'nom_poste'   => $nouveauNom,
                'etat'        => Poste::ETAT_DESACTIVE,
                'agent_token' => Str::random(64),
            ]);
        });

        Log::info("Poste « {$nom} » retiré (désinstallation depuis le poste) : renommé « {$nouveauNom} », désactivé, historique conservé.");

        return response()->json([
            'ok'       => true,
            'supprime' => false,
            'message'  => "Poste « {$nom} » retiré : il a un historique de sessions (conservé). Il reste dans l'interface web, désactivé et renommé « {$nouveauNom} » : vous pouvez l'y supprimer.",
        ]);
    }
}