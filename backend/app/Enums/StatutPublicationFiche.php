<?php

namespace App\Enums;

enum StatutPublicationFiche: string
{
    case Brouillon = 'brouillon';
    case Publiee = 'publiee';
    case Suspendue = 'suspendue';
    case Archivee = 'archivee';

    public function label(): string
    {
        return match ($this) {
            self::Brouillon => 'Brouillon',
            self::Publiee => 'Publiée',
            self::Suspendue => 'Suspendue',
            self::Archivee => 'Archivée',
        };
    }
}
