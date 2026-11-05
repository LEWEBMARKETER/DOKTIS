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

    // Pays de lancement : le Gabon. Fuseau utilisé pour « ouvert maintenant »
    // (les horaires des cabinets sont saisis en heure locale).
    'pays' => env('DOKTA_PAYS', 'Gabon'),
    'fuseau_horaire_local' => env('DOKTA_FUSEAU_LOCAL', 'Africa/Libreville'),

    // Indicatif ajouté aux numéros saisis au format national (077 12 34 56
    // devient 24177123456, le format attendu par un lien wa.me).
    'indicatif_pays' => env('DOKTA_INDICATIF_PAYS', '241'),

    // Premier compte Super Admin, créé au déploiement par ReferentielSeeder
    // s'il n'existe pas encore (voir docs/DEPLOIEMENT.md).
    'super_admin' => [
        'email' => env('DOKTA_SUPERADMIN_EMAIL'),
        'password' => env('DOKTA_SUPERADMIN_PASSWORD'),
        'nom' => env('DOKTA_SUPERADMIN_NOM', 'Équipe DOKTIS'),
    ],

];
