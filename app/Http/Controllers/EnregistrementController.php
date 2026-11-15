<?php

namespace App\Http\Controllers;

use App\Models\Poste;
use App\Services\CodeInstallation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Page « Enregistrement » : code d'installation, renommage des postes, conflits d'identité (PC clonés).
 */
class EnregistrementController extends Controller
{
    public function index(Request $request)
    {
        return view('enregistrement.index', [
            'code'       => CodeInstallation::obtenir(),
            'urlServeur' => $request->getSchemeAndHttpHost(),
            'postes'     => Poste::orderBy('nom_poste')->get(),
        ]);
    }

    public function regenererCode(): RedirectResponse
    {
        CodeInstallation::regenerer();
        Log::info("Code d'installation régénéré.");

        return redirect()->route('enregistrement.index')
            ->with('success', "Nouveau code généré. Les postes déjà enregistrés continuent de fonctionner ; seuls les nouveaux postes devront utiliser ce code.");
    }

    public function renommer(Request $request, Poste $poste): RedirectResponse
    {
        $data = $request->validate(
            ['nom_poste' => ['required', 'string', 'max:100']],
            [
                'nom_poste.required' => 'Le nom du poste est obligatoire.',
                'nom_poste.max'      => 'Le nom du poste ne doit pas dépasser 100 caractères.',
            ]
        );

        $nom = Poste::nettoyerNom($data['nom_poste']);
        if ($nom === '') {
            return back()->with('error', 'Le nom du poste est obligatoire.');
        }
        if (Poste::nomExiste($nom, $poste->id)) {
            return back()->with('error', "Le nom « {$nom} » est déjà utilisé par un autre poste.");
        }

        $ancien = $poste->nom_poste;
        $poste->update(['nom_poste' => $nom]);
        Log::info("Poste renommé : « {$ancien} » → « {$nom} ».");

        return redirect()->route('enregistrement.index')
            ->with('success', "Poste renommé : « {$ancien} » → « {$nom} ». Le poste affiche son nouveau nom en quelques secondes.");
    }

    /**
     * Sépare deux ordinateurs qui partagent la même identité : le jeton est renouvelé, les deux
     * ordinateurs se ré-identifient, et celui dont l'adresse MAC n'est pas celle du poste d'origine
     * reçoit une nouvelle identité (il apparaît alors comme un nouveau poste).
     */
    public function separer(Poste $poste): RedirectResponse
    {
        if (!$poste->aConflitIdentite()) {
            return back()->with('error', "Aucun conflit n'est signalé pour ce poste.");
        }

        $poste->update(['agent_token' => Str::random(64), 'conflit_identite_at' => now()]);
        Log::warning("Poste « {$poste->nom_poste} » : séparation des ordinateurs demandée (jeton renouvelé).");

        return back()->with('success', "Séparation lancée pour « {$poste->nom_poste} ». Dans quelques secondes, l'ordinateur en trop apparaîtra comme un nouveau poste (à renommer).");
    }

    /** Efface l'alerte (fausse alerte, ou problème réglé à la main). Elle revient si le conflit persiste. */
    public function ignorerConflit(Poste $poste): RedirectResponse
    {
        $poste->update(['conflit_identite_at' => null]);

        return back()->with('success', "Alerte effacée pour « {$poste->nom_poste} ».");
    }
}