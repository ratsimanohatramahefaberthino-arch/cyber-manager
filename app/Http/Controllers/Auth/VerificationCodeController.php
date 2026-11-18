<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class VerificationCodeController extends Controller
{
    public function show()
    {
        if (Auth::user()->email_verified_at) {
            return redirect()->route('dashboard');
        }
        return view('auth.verify-code');
    }

    public function verify(Request $request)
    {
        $request->validate(['code' => ['required', 'string', 'size:6']]);
        $user = Auth::user();

        if ($user->verifierCode($request->code)) {
            $user->marquerEmailVerifie();
            return redirect()->route('dashboard')->with('success', 'Votre adresse email a été vérifiée avec succès.');
        }

        return back()->withErrors(['code' => 'Le code est incorrect ou a expiré.']);
    }

    public function resend()
    {
        $user = Auth::user();
        $code = $user->genererCodeVerification();

        try {
            Mail::raw("Bonjour {$user->name},\n\nVoici votre nouveau code de vérification : {$code}\n\nCe code expire dans 15 minutes.", function ($message) use ($user) {
                $message->to($user->email)->subject('Nouveau code de vérification - Cyber Manager');
            });
            $msg = "Un nouveau code a été envoyé à {$user->email}.";
        } catch (\Throwable $e) {
            $msg = "L'email n'a pas pu être envoyé. Voici le code : {$code}";
        }

        return back()->with('success', $msg);
    }
}