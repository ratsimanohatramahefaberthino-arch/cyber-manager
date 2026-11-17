<?php

namespace App\Http\Controllers;

use App\Services\EnvService;
use Illuminate\Http\Request;

class ProductionController extends Controller
{
    public function index(EnvService $env)
    {
        return view('production.index', ['etat' => $env->diagnostic()]);
    }

    public function appliquer(Request $request, EnvService $env)
    {
        $d = $request->validate(['mode' => ['required', 'in:production,test']]);

        try {
            $env->basculer($d['mode'] === 'production');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('parametres.production.index')->with('success',
            $d['mode'] === 'production'
                ? 'Mode production activé. Une sauvegarde de la configuration a été conservée.'
                : 'Retour au mode test. Une sauvegarde de la configuration a été conservée.');
    }
}