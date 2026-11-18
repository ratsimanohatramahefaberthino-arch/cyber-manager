<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name'  => ['required', 'string', 'max:60'],
            'email' => ['required', 'email:filter', 'max:120', Rule::unique('users', 'email')->ignore($user->id)],
            'avatar' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ], [
            'name.required'  => 'Le nom est obligatoire.',
            'email.required' => 'L\'adresse e-mail est obligatoire.',
            'email.email'    => 'L\'adresse e-mail n\'est pas valide.',
            'email.unique'   => 'Cette adresse e-mail est déjà utilisée.',
            'avatar.image'   => 'Le fichier doit être une image.',
            'avatar.mimes'   => 'Format accepté : PNG, JPG ou WebP.',
            'avatar.max'     => 'L\'image ne doit pas dépasser 2 Mo.',
        ]);

        $user->name  = trim($data['name']);
        $user->email = strtolower(trim($data['email']));

        if ($request->hasFile('avatar')) {
            if (filled($user->avatar) && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }
            $user->avatar = $request->file('avatar')->store('avatars', 'public');
            $user->avatar_maj_at = now();
        }

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return back()->with('success', 'Profil mis à jour.');
    }

    public function supprimerAvatar(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (filled($user->avatar) && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }
        $user->avatar = null;
        $user->avatar_maj_at = null;
        $user->save();

        return back()->with('success', 'Photo de profil supprimée.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'confirmed', Password::min(8)],
        ], [
            'current_password.required'        => 'Le mot de passe actuel est obligatoire.',
            'current_password.current_password' => 'Le mot de passe actuel est incorrect.',
            'password.required'                => 'Le nouveau mot de passe est obligatoire.',
            'password.confirmed'               => 'Les deux mots de passe ne sont pas identiques.',
            'password.min'                     => 'Le mot de passe doit contenir au moins 8 caractères.',
        ]);

        $request->user()->update([
            'password' => $data['password'],
        ]);

        return back()->with('success', 'Mot de passe mis à jour.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        // La suppression de compte est réservée à l'admin et ne concerne pas le sien.
        // Cette méthode n'est pas exposée pour l'auto-suppression.
        abort(403, 'Action non autorisée.');
    }
}