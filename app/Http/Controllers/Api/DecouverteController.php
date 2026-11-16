<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CyberSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class DecouverteController extends Controller
{
    /** Public : sert à reconnaître CE serveur (signature + identifiant). Aucun secret. */
    public function show(): JsonResponse
    {
        $id = (string) CyberSetting::query()->where('cle', 'serveur_id')->value('valeur');
        if ($id === '') {
            // Secours si la migration n'a pas été lancée
            $id = (string) Str::uuid();
            CyberSetting::definir('serveur_id', $id);
        }

        return response()->json([
            'cyber_manager' => true,
            'serveur_id'    => $id,
            'nom_cyber'     => CyberSetting::tous()['nom_cyber'],
        ]);
    }
}