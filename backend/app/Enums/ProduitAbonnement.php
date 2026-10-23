<?php

namespace App\Enums;

enum ProduitAbonnement: string
{
    case Office = 'office';
    case DirectoryStructure = 'directory_structure';
    case DirectoryProfessionnel = 'directory_professionnel';

    public function label(): string
    {
        return match ($this) {
            self::Office => 'DOKTIS Office',
            self::DirectoryStructure => 'DOKTIS Structure',
            self::DirectoryProfessionnel => 'DOKTIS Professionnel',
        };
    }

    /** Prix annuel de référence en FCFA pour les offres Directory (Partie G du cahier des charges). */
    public function prixAnnuelReference(): ?int
    {
        return match ($this) {
            self::DirectoryStructure => 100000,
            self::DirectoryProfessionnel => 50000,
            self::Office => null,
        };
    }
}
