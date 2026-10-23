<?php

/**
 * Activation des modules DOKTIS, vérifiée côté serveur (middleware
 * EnsureFeatureEnabled) et pas seulement masquée côté frontend — cf.
 * mission Partie A.D : "Ces flags doivent être vérifiés côté backend pour
 * les routes sensibles, et pas seulement masquer les boutons dans le
 * frontend."
 *
 * Désactiver un module ne supprime ni ne touche aux données existantes :
 * il rend seulement ses routes inaccessibles (403) tant qu'il est éteint.
 */
return [
    'directory' => (bool) env('DOKTIS_DIRECTORY_ENABLED', true),
    'office' => (bool) env('DOKTIS_OFFICE_ENABLED', false),
    'patient_medical' => (bool) env('DOKTIS_PATIENT_MEDICAL_ENABLED', false),
];
