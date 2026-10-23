<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloque une route au niveau serveur quand le module correspondant est
 * désactivé (config('features.*'), voir config/features.php), même en
 * tapant directement l'URL — jamais un simple masquage côté frontend
 * (mission Partie A.D et Partie I).
 */
class EnsureFeatureEnabled
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        abort_unless(config("features.{$feature}") === true, 403, 'Cette fonctionnalité est désactivée sur cette instance de DOKTIS.');

        return $next($request);
    }
}
