<?php

namespace App\Http\Controllers;

use App\Models\Poste;
use App\Services\PosteService;
use Illuminate\Http\RedirectResponse;

class PosteController extends Controller
{
    public function index()
    {
        $postes = Poste::orderBy('nom_poste')->get();

        return view('postes.index', compact('postes'));
    }

    public function testerAgent(
        Poste $poste,
        PosteService $posteService
    ): RedirectResponse {
        try {
            $resultat = $posteService->testerAgent($poste);

            if ($resultat['succes']) {
                return redirect()
                    ->route('postes.index')
                    ->with(
                        'success',
                        "Agent de {$poste->nom_poste} accessible et opérationnel."
                    );
            }

            return redirect()
                ->route('postes.index')
                ->with(
                    'error',
                    $resultat['message']
                    ?? "Impossible de communiquer avec l'agent."
                );
        } catch (\Throwable $exception) {
            return redirect()
                ->route('postes.index')
                ->with(
                    'error',
                    $exception->getMessage()
                );
        }
    }

    public function verrouiller(
        Poste $poste,
        PosteService $posteService
    ): RedirectResponse {
        try {
            $resultat = $posteService->verrouillerPoste($poste);

            if ($resultat['succes']) {
                return redirect()
                    ->route('postes.index')
                    ->with(
                        'success',
                        "Commande de verrouillage envoyée à {$poste->nom_poste}."
                    );
            }

            return redirect()
                ->route('postes.index')
                ->with(
                    'error',
                    $resultat['message']
                    ?? "Impossible de verrouiller le poste."
                );
        } catch (\Throwable $exception) {
            return redirect()
                ->route('postes.index')
                ->with(
                    'error',
                    $exception->getMessage()
                );
        }
    }

    public function deverrouiller(
        Poste $poste,
        PosteService $posteService
    ): RedirectResponse {
        try {
            $resultat = $posteService->deverrouillerPoste($poste);

            if ($resultat['succes']) {
                return redirect()
                    ->route('postes.index')
                    ->with(
                        'success',
                        "Commande de déverrouillage envoyée à {$poste->nom_poste}."
                    );
            }

            return redirect()
                ->route('postes.index')
                ->with(
                    'error',
                    $resultat['message']
                    ?? "Impossible de déverrouiller le poste."
                );
        } catch (\Throwable $exception) {
            return redirect()
                ->route('postes.index')
                ->with(
                    'error',
                    $exception->getMessage()
                );
        }
    }
}