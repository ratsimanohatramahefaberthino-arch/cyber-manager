<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Usage : ->middleware(RoleMiddleware::class . ':admin') */
class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        if (!in_array($user->role, $roles, true)) {
            $message = 'Cette action est réservée à l\'administrateur.';

            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'message' => $message], 403);
            }

            return redirect()->route('dashboard')->with('acces_refuse', $message);
        }

        return $next($request);
    }
}