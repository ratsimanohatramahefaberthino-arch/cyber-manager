<?php

namespace App\Http\Controllers;

use App\Models\Poste;
use App\Services\CodeInstallation;
use App\Services\ReseauService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class EnregistrementController extends Controller
{
    private const FUSEAU = 'Indian/Antananarivo';

    public function index(Request $request)
    {
        $postes = Poste::all()->sortBy('nom_poste', SORT_NATURAL | SORT_FLAG_CASE)->values();

        return view('enregistrement.index', [
            'code'       => CodeInstallation::obtenir(),
            'urlServeur' => ReseauService::urlServeur((int) $request->getPort()),
            'ips'        => ReseauService::toutes(),
            'nomExe'     => InstallationController::EXE,
            'postes'     => $postes,
            'conflits'   => $postes->filter(fn (Poste $p) => $p->aConflitIdentite())->values(),
            'nbEnLigne'  => $postes->filter(fn (Poste $p) => $p->estEnLigne())->count(),
            'fuseau'     => self::FUSEAU,
        ]);
    }

    public function regenererCode(): RedirectResponse
    {
        CodeInstallation::regenerer();

        return redirect()->route('enregistrement.index')
            ->with('success', "Nouveau code généré. Les postes déjà enregistrés continuent de fonctionner ; seuls les nouveaux postes devront utiliser ce code.");
    }

    public function renommer(Request $request, Poste $poste): RedirectResponse
    {
        $validation = Validator::make(
            $request->all(),
            ['nom_poste' => ['required', 'string', 'max:100']],
            [
                'nom_poste.required' => 'Le nom du poste est obligatoire.',
                'nom_poste.max'      => 'Le nom du poste ne doit pas dépasser 100 caractères.',
            ]
        );

        if ($validation->fails()) {
            return back()->with('error', $validation->errors()->first());
        }

        $nom = Poste::nettoyerNom((string) $request->input('nom_poste'));
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

    public function separer(Poste $poste): RedirectResponse
    {
        if (!$poste->aConflitIdentite()) {
            return back()->with('error', "Aucun conflit n'est signalé pour ce poste.");
        }

        $poste->update(['agent_token' => Str::random(64), 'conflit_identite_at' => now()]);
        Log::warning("Poste « {$poste->nom_poste} » : séparation des ordinateurs demandée (jeton renouvelé).");

        return back()->with('success', "Séparation lancée pour « {$poste->nom_poste} ». Dans quelques secondes, l'ordinateur en trop apparaîtra comme un nouveau poste (à renommer).");
    }

    public function ignorerConflit(Poste $poste): RedirectResponse
    {
        $poste->update(['conflit_identite_at' => null]);

        return back()->with('success', "Alerte effacée pour « {$poste->nom_poste} ».");
    }
}