<?php

namespace App\Http\Controllers;

use App\Models\Tarif;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TarifController extends Controller
{
    public function index()
    {
        $tarifs = Tarif::orderByDesc('actif')
            ->orderBy('nom')
            ->get();

        return view('tarifs.index', compact('tarifs'));
    }

    public function store(Request $request): RedirectResponse
    {
        $donnees = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'montant_par_minute' => ['required', 'integer', 'min:1'],
            'montant_minimum' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
        ]);

        Tarif::query()->update([
            'actif' => false,
        ]);

        Tarif::create([
            ...$donnees,
            'actif' => true,
            'personnalise' => true,
        ]);

        return redirect()
            ->route('tarifs.index')
            ->with('success', 'Tarif créé et activé avec succès.');
    }

    public function activer(Tarif $tarif): RedirectResponse
    {
        Tarif::query()->update([
            'actif' => false,
        ]);

        $tarif->update([
            'actif' => true,
        ]);

        return redirect()
            ->route('tarifs.index')
            ->with(
                'success',
                "Le tarif {$tarif->nom} est maintenant actif."
            );
    }

    public function destroy(Tarif $tarif): RedirectResponse
    {
        if ($tarif->sessions()->exists()) {
            return redirect()
                ->route('tarifs.index')
                ->with(
                    'error',
                    'Ce tarif est déjà utilisé par une session et ne peut pas être supprimé.'
                );
        }

        $tarif->delete();

        return redirect()
            ->route('tarifs.index')
            ->with('success', 'Tarif supprimé.');
    }
}