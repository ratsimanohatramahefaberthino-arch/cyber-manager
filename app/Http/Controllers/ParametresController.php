<?php

namespace App\Http\Controllers;

use App\Models\CyberSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ParametresController extends Controller
{
    public function index()
    {
        return view('parametres.index', [
            'p'           => CyberSetting::tous(),
            'logo'        => CyberSetting::logoExiste(),
            'logoVersion' => CyberSetting::logoVersion(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nom_cyber'          => ['required', 'string', 'max:60'],
            'slogan'             => ['nullable', 'string', 'max:80'],
            'nom_admin'          => ['nullable', 'string', 'max:60'],
            'couleur_principale' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo'               => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:2048',
                                     'dimensions:min_width=64,min_height=64,max_width=4000,max_height=4000'],
        ], [
            'nom_cyber.required' => 'Le nom du cyber est obligatoire.',
            'logo.image'         => 'Le logo doit être une image.',
            'logo.mimes'         => 'Le logo doit être au format PNG ou JPG.',
            'logo.max'           => 'Le logo ne doit pas dépasser 2 Mo.',
            'logo.dimensions'    => 'Le logo doit mesurer au moins 64 × 64 px (et au plus 4000 × 4000 px).',
        ]);

        foreach (['nom_cyber', 'slogan', 'nom_admin', 'couleur_principale'] as $cle) {
            CyberSetting::definir($cle, trim((string) ($data[$cle] ?? '')));
        }

        if ($request->hasFile('logo')) {
            $ancien  = CyberSetting::tous()['logo_path'];
            $fichier = $request->file('logo');
            $ext     = strtolower($fichier->extension() ?: 'png');
            $chemin  = $fichier->storeAs('logos', 'logo-' . now()->timestamp . '.' . $ext, 'public');

            if ($chemin) {
                CyberSetting::definir('logo_path', $chemin);
                if ($ancien && $ancien !== $chemin) {
                    Storage::disk('public')->delete($ancien);
                }
            }
        }

        return redirect()->route('parametres.index')->with('success', 'Paramètres enregistrés. Les postes se mettent à jour en quelques secondes.');
    }

    public function supprimerLogo(): RedirectResponse
    {
        $ancien = CyberSetting::tous()['logo_path'];
        if ($ancien) {
            Storage::disk('public')->delete($ancien);
        }
        CyberSetting::definir('logo_path', null);

        return redirect()->route('parametres.index')->with('success', 'Logo supprimé.');
    }
}