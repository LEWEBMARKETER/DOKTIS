<?php

/*
|--------------------------------------------------------------------------
| Modules DOKTA
|--------------------------------------------------------------------------
|
| La V1 de DOKTIS est un annuaire avec demande de rendez-vous par WhatsApp.
| Les modules « SaaS » (DOKTA Office : dossiers, agenda, facturation...) et
| « Patient » (comptes patients, rendez-vous enregistrés, dossier médical)
| restent dans le code pour la V2 mais sont désactivés par défaut : leurs
| routes répondent 404 tant que le drapeau correspondant vaut false.
|
*/

return [

    'modules' => [
        'saas' => (bool) env('DOKTA_MODULE_SAAS', false),
        'patient' => (bool) env('DOKTA_MODULE_PATIENT', false),
    ],

    // Fuseau utilisé pour « ouvert maintenant » (les horaires des cabinets
    // sont saisis en heure locale).
    'fuseau_horaire_local' => env('DOKTA_FUSEAU_LOCAL', 'Africa/Douala'),

];
