<?php

namespace App\Http\Middleware;

use App\Models\PatientAccount;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePatientAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() instanceof PatientAccount) {
            abort(403, 'Ce compte ne peut pas accéder aux fonctionnalités patient.');
        }

        return $next($request);
    }
}
