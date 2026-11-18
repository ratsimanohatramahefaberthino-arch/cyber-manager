<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ForceEmailVerification
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && !$user->email_verified_at
            && !$request->routeIs('verification.*', 'logout', 'profile.*', 'parametres.profil')) {
            return redirect()->route('verification.notice.custom');
        }

        return $next($request);
    }
}