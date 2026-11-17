<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class PersonnelController extends Controller
{
    private const MESSAGES = [
        'name.required'      => 'Le nom est obligatoire.',
        'email.required'     => 'L\'adresse e-mail est obligatoire.',
        'email.email'        => 'L\'adresse e-mail n\'est pas valide.',
        'email.unique'       => 'Cette adresse e-mail est déjà utilisée par un autre compte.',
        'password.required'  => 'Le mot de passe est obligatoire.',
        'password.confirmed' => 'Les deux mots de passe ne sont pas identiques.',
        'password.min'       => 'Le mot de passe doit contenir au moins 8 caractères.',
        'role.required'      => 'Choisissez un rôle.',
        'role.in'            => 'Rôle invalide.',
    ];

    private function nbAdmins(): int
    {
        return User::where('role', User::ROLE_ADMIN)->count();
    }

    public function index()
    {
        return view('parametres.personnel', [
            'utilisateurs' => User::orderByRaw("role = 'admin' desc")->orderBy('name')->get(),
            'moi'          => auth()->id(),
            'nbAdmins'     => $this->nbAdmins(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:60'],
            'email'    => ['required', 'email:filter', 'max:120', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'role'     => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_PERSONNEL])],
        ], self::MESSAGES);

        $user = new User([
            'name'     => trim($data['name']),
            'email'    => strtolower(trim($data['email'])),
            'password' => $data['password'],   // haché automatiquement (cast « hashed »)
        ]);
        $user->role = $data['role'];
        $user->save();

        return back()->with('success', "Compte de « {$user->name} » créé ({$user->libelleRole()}).");
    }

    public function update(Request $request, User $utilisateur): RedirectResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:60'],
            'email'    => ['required', 'email:filter', 'max:120', Rule::unique('users', 'email')->ignore($utilisateur->id)],
            'password' => ['nullable', 'confirmed', Password::min(8)],
            'role'     => ['nullable', Rule::in([User::ROLE_ADMIN, User::ROLE_PERSONNEL])],
        ], self::MESSAGES);

        $estMoi = $utilisateur->id === auth()->id();
        $role   = $estMoi ? $utilisateur->role : ($data['role'] ?? $utilisateur->role);

        if ($utilisateur->estAdmin() && $role !== User::ROLE_ADMIN && $this->nbAdmins() <= 1) {
            return back()->withErrors(['Il doit toujours rester au moins un administrateur.']);
        }

        $utilisateur->name  = trim($data['name']);
        $utilisateur->email = strtolower(trim($data['email']));
        $utilisateur->role  = $role;
        if (!empty($data['password'])) {
            $utilisateur->password = $data['password'];
        }
        $utilisateur->save();

        return back()->with('success', "Compte de « {$utilisateur->name} » mis à jour.");
    }

    public function destroy(User $utilisateur): RedirectResponse
    {
        if ($utilisateur->id === auth()->id()) {
            return back()->withErrors(['Vous ne pouvez pas supprimer votre propre compte.']);
        }
        if ($utilisateur->estAdmin() && $this->nbAdmins() <= 1) {
            return back()->withErrors(['Il doit toujours rester au moins un administrateur.']);
        }

        $nom = $utilisateur->name;
        $utilisateur->delete();

        return back()->with('success', "Compte de « {$nom} » supprimé.");
    }
}