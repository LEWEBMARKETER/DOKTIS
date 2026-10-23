<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Distingue les abonnements DOKTIS Office (existants) des deux offres
 * Directory ("DOKTIS STRUCTURE" et "DOKTIS PROFESSIONNEL"), sans toucher
 * aux abonnements déjà enregistrés (tous rattachés par défaut à "office").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('abonnements', function (Blueprint $table) {
            $table->string('produit')->default('office')->after('cabinet_id');
        });

        DB::statement("ALTER TABLE abonnements ADD CONSTRAINT abonnements_produit_check CHECK (produit IN ('office','directory_structure','directory_professionnel'))");

        // Ajoute le statut "expire", distinct de "annule" (annulation
        // volontaire) : atteint automatiquement par un job planifié quand
        // date_fin + période de grâce est dépassée, sans jamais supprimer
        // la fiche ni ses données (cf. mission Partie G, "politique
        // d'expiration").
        DB::statement('ALTER TABLE abonnements DROP CONSTRAINT abonnements_statut_check');
        DB::statement("ALTER TABLE abonnements ADD CONSTRAINT abonnements_statut_check CHECK (statut IN ('actif','suspendu','annule','expire'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE abonnements DROP CONSTRAINT abonnements_statut_check');
        DB::statement("ALTER TABLE abonnements ADD CONSTRAINT abonnements_statut_check CHECK (statut IN ('actif','suspendu','annule'))");

        Schema::table('abonnements', function (Blueprint $table) {
            $table->dropColumn('produit');
        });
    }
};
