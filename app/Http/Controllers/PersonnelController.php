<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Mail;

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
        'password.mixed'     => 'Le mot de passe doit contenir au moins une majuscule et une minuscule.',
        'password.numbers'   => 'Le mot de passe doit contenir au moins un chiffre.',
        'role.required'      => 'Choisissez un rôle.',
        'role.in'            => 'Rôle invalide.',
        'avatar.image'       => 'Le fichier doit être une image.',
        'avatar.mimes'       => 'Format accepté : PNG, JPG ou WebP.',
        'avatar.max'         => 'L\'image ne doit pas dépasser 2 Mo.',
    ];

    private function nbAdmins(): int
    {
        return User::where('role', User::ROLE_ADMIN)->count();
    }

    public function index()
    {
        $utilisateurs = User::orderByRaw("role = 'admin' desc")->orderBy('name')->get();

        return view('parametres.personnel', [
            'utilisateurs' => $utilisateurs,
            'moi'          => auth()->id(),
            'nbAdmins'     => $utilisateurs->where('role', User::ROLE_ADMIN)->count(),
            'nbPersonnels' => $utilisateurs->where('role', User::ROLE_PERSONNEL)->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'      => ['required', 'string', 'max:60'],
            'email'     => ['required', 'email:filter', 'max:120', 'unique:users,email'],
            'password'  => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
            'role'      => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_PERSONNEL])],
            'telephone' => ['nullable', 'string', 'max:30'],
            'notes_internes' => ['nullable', 'string', 'max:1000'],
            'avatar'    => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ], self::MESSAGES);

        $user = new User([
            'name'           => trim($data['name']),
            'email'          => strtolower(trim($data['email'])),
            'password'       => $data['password'],
            'telephone'      => $data['telephone'] ?? null,
            'notes_internes' => $data['notes_internes'] ?? null,
        ]);
        $user->role = $data['role'];

        if ($request->hasFile('avatar')) {
            $user->avatar = $request->file('avatar')->store('avatars', 'public');
            $user->avatar_maj_at = now();
        }

        $user->save();

        $code = $user->genererCodeVerification();

        try {
            Mail::raw("Bonjour {$user->name},\n\nVotre compte Cyber Manager a été créé.\nVoici votre code de vérification : {$code}\n\nCe code expire dans 15 minutes.", function ($message) use ($user) {
                $message->to($user->email)->subject('Votre code de vérification - Cyber Manager');
            });
            $msgMail = "Un code de vérification a été envoyé à {$user->email}.";
        } catch (\Throwable $e) {
            $msgMail = "L'email n'a pas pu être envoyé. Voici le code de vérification à transmettre au personnel : {$code}";
        }

        return back()->with('success', "Compte de « {$user->name} » créé ({$user->libelleRole()}). {$msgMail}");
    }

    public function update(Request $request, User $utilisateur): RedirectResponse
    {
        $data = $request->validate([
            'name'      => ['required', 'string', 'max:60'],
            'email'     => ['required', 'email:filter', 'max:120', Rule::unique('users', 'email')->ignore($utilisateur->id)],
            'password'  => ['nullable', 'confirmed', Password::min(8)->mixedCase()->numbers()],
            'role'      => ['nullable', Rule::in([User::ROLE_ADMIN, User::ROLE_PERSONNEL])],
            'telephone' => ['nullable', 'string', 'max:30'],
            'notes_internes' => ['nullable', 'string', 'max:1000'],
            'avatar'    => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ], self::MESSAGES);

        $estMoi = $utilisateur->id === auth()->id();
        $role   = $estMoi ? $utilisateur->role : ($data['role'] ?? $utilisateur->role);

        if ($utilisateur->estAdmin() && $role !== User::ROLE_ADMIN && $this->nbAdmins() <= 1) {
            return back()->withErrors(['Il doit toujours rester au moins un administrateur.']);
        }

        $utilisateur->name           = trim($data['name']);
        $utilisateur->email          = strtolower(trim($data['email']));
        $utilisateur->telephone      = $data['telephone'] ?? null;
        $utilisateur->notes_internes = $data['notes_internes'] ?? null;
        $utilisateur->role           = $role;

        if (!empty($data['password'])) {
            $utilisateur->password = $data['password'];
        }

        if ($request->hasFile('avatar')) {
            if (filled($utilisateur->avatar) && Storage::disk('public')->exists($utilisateur->avatar)) {
                Storage::disk('public')->delete($utilisateur->avatar);
            }
            $utilisateur->avatar = $request->file('avatar')->store('avatars', 'public');
            $utilisateur->avatar_maj_at = now();
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

        if (filled($utilisateur->avatar) && Storage::disk('public')->exists($utilisateur->avatar)) {
            Storage::disk('public')->delete($utilisateur->avatar);
        }

        $utilisateur->delete();

        return back()->with('success', "Compte de « {$nom} » supprimé.");
    }

    public function reinitialiserMotDePasse(User $utilisateur): JsonResponse
    {
        if ($utilisateur->id === auth()->id()) {
            return response()->json(['ok' => false, 'message' => 'Utilisez « Mon profil » pour changer votre propre mot de passe.'], 422);
        }

        $nouveau = Str::random(12);
        $utilisateur->password = $nouveau;
        $utilisateur->save();

        return response()->json([
            'ok'       => true,
            'message'  => "Nouveau mot de passe pour « {$utilisateur->name} » : {$nouveau}",
            'password' => $nouveau,
        ]);
    }

    public function deconnecterPartout(User $utilisateur): JsonResponse
    {
        if ($utilisateur->id === auth()->id()) {
            return response()->json(['ok' => false, 'message' => 'Vous ne pouvez pas vous déconnecter vous-même par cette action.'], 422);
        }

        \DB::table('sessions')->where('user_id', $utilisateur->id)->delete();
        \DB::table('users')->where('id', $utilisateur->id)->update(['remember_token' => null]);

        return response()->json([
            'ok'      => true,
            'message' => "Toutes les sessions de « {$utilisateur->name} » ont été fermées.",
        ]);
    }

    public function supprimerAvatar(User $utilisateur): RedirectResponse
    {
        if (filled($utilisateur->avatar) && Storage::disk('public')->exists($utilisateur->avatar)) {
            Storage::disk('public')->delete($utilisateur->avatar);
        }
        $utilisateur->avatar = null;
        $utilisateur->avatar_maj_at = null;
        $utilisateur->save();

        return back()->with('success', "Avatar de « {$utilisateur->name} » supprimé.");
    }

    public function debloquer(User $utilisateur): JsonResponse
    {
        $utilisateur->debloquer();
        return response()->json([
            'ok' => true,
            'message' => "Le compte de « {$utilisateur->name} » a été débloqué."
        ]);
    }
}