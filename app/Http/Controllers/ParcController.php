<?php

namespace App\Http\Controllers;

use App\Models\AgentVersion;
use App\Models\Poste;
use App\Models\PosteCommande;
use App\Services\RestrictionsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ParcController extends Controller
{
    public function index()
    {
        $postes = Poste::orderBy('nom_poste')->get();

        $visees = [];
        foreach ($postes as $p) {
            $visees[$p->id] = AgentVersion::viseePour($p)?->version;
        }

        return view('parametres.parc', [
            'postes' => $postes,
            'visees' => $visees,
            'profil' => RestrictionsService::profil(),
            'cles'   => RestrictionsService::CLES,
        ]);
    }

    public function renommer(Request $request, Poste $poste): RedirectResponse
    {
        $request->validate(['nom' => ['required', 'string', 'max:100']], ['nom.required' => 'Le nom est obligatoire.']);

        $nom = Poste::nettoyerNom((string) $request->input('nom'));
        if ($nom === '') {
            return back()->withErrors(['Nom invalide.']);
        }
        if (Poste::nomExiste($nom, $poste->id)) {
            return back()->withErrors(["Le nom « {$nom} » est déjà pris par un autre poste."]);
        }

        $poste->update(['nom_poste' => $nom]);

        return back()->with('success', "Poste renommé en « {$nom} ».");
    }

    public function maintenance(Poste $poste): RedirectResponse
    {
        $nouveau = !((bool) $poste->mode_maintenance);
        $poste->forceFill(['mode_maintenance' => $nouveau])->save();

        return back()->with('success', $nouveau
            ? "Mode maintenance activé pour « {$poste->nom_poste} » : restrictions levées dans quelques secondes."
            : "Mode maintenance désactivé pour « {$poste->nom_poste} » : restrictions remises.");
    }

    /** Récupérer les 30 derniers logs d'un poste (Modal) */
    public function logs(Poste $poste): JsonResponse
    {
        $logs = PosteCommande::where('poste_id', $poste->id)
            ->orderByDesc('created_at')
            ->limit(30)
            ->get()
            ->map(function($c) {
                return [
                    'date' => $c->created_at->timezone('Indian/Antananarivo')->format('d/m/Y H:i:s'),
                    'commande' => $c->type ?? 'Inconnu',
                    'statut' => $c->statut,
                    'resultat' => $c->resultat ?? 'Aucun retour',
                ];
            });

        return response()->json(['logs' => $logs]);
    }

    /** Envoyer un ordre de redémarrage au poste via la file d'attente */
    public function redemarrer(Poste $poste): JsonResponse
    {
        if (!$poste->estEnLigne()) {
            return response()->json(['ok' => false, 'message' => 'Le poste est hors ligne, impossible de le redémarrer.'], 422);
        }

        PosteCommande::create([
            'poste_id' => $poste->id,
            'type'     => 'redemarrer',
            'statut'   => 'en_attente',
            'payload'  => null,
        ]);

        return response()->json(['ok' => true, 'message' => "Ordre de redémarrage envoyé à « {$poste->nom_poste} »."]);
    }
}