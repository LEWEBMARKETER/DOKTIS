<?php

/**
 * Paramètres propres au produit DOKTIS Directory.
 */
return [
    // Nombre de jours après la date d'expiration d'un abonnement Directory
    // pendant lesquels la fiche reste publiée malgré l'échéance dépassée
    // (mission Partie G, "politique d'expiration" : pas de suppression
    // automatique, période de grâce paramétrable).
    'jours_grace_abonnement' => (int) env('DOKTIS_JOURS_GRACE_ABONNEMENT', 15),

    // Base des liens d'activation partenaire envoyés par un administrateur
    // (jamais lus via env() en dehors d'un fichier config/, pour rester
    // compatible avec `config:cache` en production).
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:3000'),

    // Durée de validité d'un lien d'invitation partenaire.
    'invitation_expire_jours' => (int) env('DOKTIS_INVITATION_EXPIRE_JOURS', 7),
];
