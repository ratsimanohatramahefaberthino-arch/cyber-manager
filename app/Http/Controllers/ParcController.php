<?php

namespace App\Http\Controllers;

use App\Models\AgentVersion;
use App\Models\Poste;
use App\Services\RestrictionsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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

    /** Le Gardien du poste lit ce drapeau (GET /api/agent/restrictions) et lève / remet les restrictions. */
    public function maintenance(Poste $poste): RedirectResponse
    {
        $nouveau = !((bool) $poste->mode_maintenance);
        $poste->forceFill(['mode_maintenance' => $nouveau])->save();

        return back()->with('success', $nouveau
            ? "Mode maintenance activé pour « {$poste->nom_poste} » : restrictions levées dans quelques secondes."
            : "Mode maintenance désactivé pour « {$poste->nom_poste} » : restrictions remises.");
    }
}