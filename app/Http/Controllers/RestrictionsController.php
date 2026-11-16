<?php

namespace App\Http\Controllers;

use App\Services\RestrictionsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RestrictionsController extends Controller
{
    public function enregistrer(Request $request): RedirectResponse
    {
        $coches = [];
        foreach (array_keys(RestrictionsService::CLES) as $cle) {
            $coches[$cle] = $request->boolean("r.$cle");
        }

        RestrictionsService::enregistrer($coches);

        return redirect()->route('parametres.parc.index')
            ->with('success', 'Profil de restrictions enregistré. Les postes l\'appliquent en moins de 15 secondes.');
    }
}