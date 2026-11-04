<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloque les routes d'un module désactivé (voir config/dokta.php).
 * On répond 404 plutôt que 403 : pour le client, la fonctionnalité n'existe pas encore.
 */
class EnsureModuleEnabled
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        abort_unless(config("dokta.modules.{$module}", false), 404);

        return $next($request);
    }
}
