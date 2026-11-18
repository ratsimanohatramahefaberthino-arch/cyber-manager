<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;

class PasswordResetCodeController extends Controller
{
    public function request()
    {
        return view('auth.forgot-password');
    }

    public function sendCode(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);
        
        $user = User::where('email', $request->email)->first();

        // Message neutre pour ne pas révéler si l'email existe
        if (!$user) {
            return back()->with('status', 'Si un compte existe avec cet email, un code vous a été envoyé.');
        }

        $code = $user->genererCodeReinitialisation();

        try {
            Mail::raw("Bonjour {$user->name},\n\nVoici votre code pour réinitialiser votre mot de passe : {$code}\n\nCe code expire dans 15 minutes.", function ($message) use ($user) {
                $message->to($user->email)->subject('Réinitialisation de mot de passe - Cyber Manager');
            });
        } catch (\Throwable $e) {
            // Si pas de mail, on ne peut rien faire ici, sauf si on est admin
        }

        // Stocke l'email en session pour l'étape suivante
        session(['reset_email' => $user->email]);

        return redirect()->route('password.reset.form')->with('status', 'Un code vous a été envoyé (vérifiez vos spams).');
    }

    public function showResetForm()
    {
        if (!session('reset_email')) {
            return redirect()->route('password.request');
        }
        return view('auth.reset-password-code');
    }

    public function reset(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string', 'size:6'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $email = session('reset_email');
        $user = User::where('email', $email)->first();

        if (!$user || !$user->verifierCodeReinitialisation($request->code)) {
            return back()->withErrors(['code' => 'Le code est incorrect ou a expiré.']);
        }

        $user->password = Hash::make($request->password);
        $user->effacerCodesReinitialisation();
        $user->save();

        session()->forget('reset_email');

        return redirect()->route('login')->with('status', 'Votre mot de passe a été modifié. Vous pouvez maintenant vous connecter.');
    }
}