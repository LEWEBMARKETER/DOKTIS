<?php

namespace App\Enums;

enum RoleUtilisateur: string
{
    case SuperAdmin = 'super_admin';
    case Administrateur = 'administrateur';
    case Medecin = 'medecin';
    case Secretaire = 'secretaire';
    case Assistant = 'assistant';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super administrateur',
            self::Administrateur => 'Administrateur',
            self::Medecin => 'Médecin / Dentiste',
            self::Secretaire => 'Secrétaire',
            self::Assistant => 'Assistant médical',
        };
    }
}
