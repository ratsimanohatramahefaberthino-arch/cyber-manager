<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // Mise à jour de l'historique de connexion (Étape 7)
        $user = Auth::user();
        $user->last_login_at = now();
        $user->last_login_ip = $request->ip();
        $user->save();

        // Mémoriser le compte pour l'Étape 8 (sans le mot de passe)
        $comptesRecents = json_decode($request->cookie('comptes_recents', '[]'), true);
        if (!is_array($comptesRecents)) {
            $comptesRecents = [];
        }

        // On retire l'ancien s'il existe déjà
        $comptesRecents = array_values(array_filter($comptesRecents, fn($c) => ($c['email'] ?? '') !== $user->email));

        // On ajoute le nouveau en haut de la liste
        array_unshift($comptesRecents, [
            'email'      => $user->email,
            'name'       => $user->name,
            'avatar_url' => $user->avatarUrl(),
            'initiales'  => $user->initiales(),
            'couleur'    => $user->couleurAvatar(),
        ]);

        // On garde maximum 3 comptes
        $comptesRecents = array_slice($comptesRecents, 0, 3);

        // On crée le cookie pour 1 an
        Cookie::queue('comptes_recents', json_encode($comptesRecents), 60 * 24 * 365);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}