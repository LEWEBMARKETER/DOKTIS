<?php

namespace App\Enums;

enum StatutVerificationFiche: string
{
    case NonVerifiee = 'non_verifiee';
    case EnAttenteVerification = 'en_attente_verification';
    case Verifiee = 'verifiee';

    public function label(): string
    {
        return match ($this) {
            self::NonVerifiee => 'Non vérifiée',
            self::EnAttenteVerification => 'En attente de vérification',
            self::Verifiee => 'Vérifiée',
        };
    }
}
