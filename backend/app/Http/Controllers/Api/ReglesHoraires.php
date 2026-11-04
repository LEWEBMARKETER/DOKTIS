<?php

namespace App\Http\Controllers\Api;

/**
 * Règles de validation partagées pour la saisie des horaires d'un cabinet.
 */
final class ReglesHoraires
{
    public static function regles(bool $requis): array
    {
        return [
            'horaires' => [$requis ? 'required' : 'sometimes', 'array', 'max:7'],
            'horaires.*.jour_semaine' => ['required', 'integer', 'min:0', 'max:6', 'distinct'],
            'horaires.*.heure_ouverture' => ['nullable', 'date_format:H:i'],
            'horaires.*.heure_fermeture' => ['nullable', 'date_format:H:i', 'after:horaires.*.heure_ouverture'],
            'horaires.*.ferme' => ['sometimes', 'boolean'],
        ];
    }
}
