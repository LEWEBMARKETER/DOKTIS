<?php

namespace App\Enums;

enum CategorieFiche: string
{
    case Structure = 'structure';
    case ProfessionnelIndependant = 'professionnel_independant';

    public function label(): string
    {
        return match ($this) {
            self::Structure => 'Structure sanitaire',
            self::ProfessionnelIndependant => 'Professionnel indépendant',
        };
    }
}
