<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * DOKTA Office et DOKTA Patient partagent le même mécanisme de jeton Sanctum
 * (polymorphe). Cette garde empêche un jeton patient d'atteindre les routes
 * réservées au personnel du cabinet, et inversement.
 */
class EnsureStaffUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() instanceof User) {
            abort(403, 'Ce compte ne peut pas accéder aux fonctionnalités du cabinet.');
        }

        return $next($request);
    }
}
