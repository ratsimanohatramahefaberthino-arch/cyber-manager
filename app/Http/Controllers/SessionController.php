<?php

namespace App\Http\Controllers;

use App\Models\Poste;
use App\Models\Session;
use App\Services\SessionService;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    public function index()
    {
        $sessions = Session::with(['poste', 'voucher'])
            ->orderByDesc('created_at')
            ->get();

        $postesDisponibles = Poste::where('actif', true)
            ->where('etat', 'disponible')
            ->orderBy('nom_poste')
            ->get();

        // Stats rapides pour l'en-tête
        $stats = [
            'total'      => $sessions->count(),
            'en_cours'   => $sessions->where('etat', 'en_cours')->count(),
            'wifi'       => $sessions->where('type_session', 'wifi')->count(),
            'ethernet'   => $sessions->where('type_session', 'ethernet')->count(),
        ];

        return view('sessions.index', compact(
            'sessions',
            'postesDisponibles',
            'stats'
        ));
    }

    public function store(
        Request $request,
        SessionService $sessionService
    ) {
        $validated = $request->validate([
            'poste_id'    => ['required', 'integer', 'exists:postes,id'],
            'montant'     => ['required', 'integer', 'min:300'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $poste = Poste::findOrFail($validated['poste_id']);

        try {
            $sessionService->creerSessionEthernet(
                $poste,
                $validated['montant'],
                $validated['description'] ?? null
            );
        } catch (\InvalidArgumentException $e) {
            return back()
                ->withInput()
                ->withErrors(['session' => $e->getMessage()]);
        }

        return redirect()
            ->route('sessions.index')
            ->with('success', 'Session Ethernet activée avec succès.');
    }
}