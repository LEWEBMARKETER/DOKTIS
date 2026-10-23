<?php

namespace App\Enums;

enum StatutDemandeRendezVous: string
{
    case EnAttente = 'en_attente';
    case Confirmee = 'confirmee';
    case Refusee = 'refusee';
    case Annulee = 'annulee';
    case Terminee = 'terminee';

    public function label(): string
    {
        return match ($this) {
            self::EnAttente => 'En attente',
            self::Confirmee => 'Confirmée',
            self::Refusee => 'Refusée',
            self::Annulee => 'Annulée',
            self::Terminee => 'Terminée',
        };
    }
}
