<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Politique de conservation des empreintes de dédoublonnage des visiteurs
// (mission Partie E) : exécution quotidienne, sans effet sur les
// compteurs agrégés "visites_fiche" qui ne sont jamais purgés.
Schedule::command('visites:purger-empreintes')->daily();
