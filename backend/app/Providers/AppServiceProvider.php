<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Limite dédiée aux endpoints d'authentification (login + inscription,
        // Office et Patient) : nettement plus stricte que le throttle "api"
        // générique (60/min), pour freiner le bourrage d'identifiants
        // (credential stuffing) sans pénaliser l'usage normal de l'API.
        // Double clé : par IP (empêche une IP de tester beaucoup de comptes)
        // et par IP+identifiant (empêche de s'acharner sur un seul compte
        // depuis des IP différentes au même rythme élevé).
        RateLimiter::for('auth', function ($request) {
            $identifiant = (string) ($request->input('email') ?? $request->input('identifiant') ?? $request->input('admin_email') ?? '');

            return [
                Limit::perMinute(20)->by('auth-ip:'.$request->ip()),
                Limit::perMinute(5)->by('auth-id:'.$request->ip().'|'.mb_strtolower($identifiant)),
            ];
        });
    }
}
