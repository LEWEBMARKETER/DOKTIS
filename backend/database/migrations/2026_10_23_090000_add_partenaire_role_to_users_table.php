<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ajoute le rôle "partenaire" (structure ou professionnel indépendant du
 * Directory, sans aucun droit DOKTIS Office) à la contrainte d'énumération
 * existante sur users.role, sans supprimer aucune valeur existante.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_role_check');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('super_admin','administrateur','medecin','secretaire','assistant','partenaire'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_role_check');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('super_admin','administrateur','medecin','secretaire','assistant'))");
    }
};
