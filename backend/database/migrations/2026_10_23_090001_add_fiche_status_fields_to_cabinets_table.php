<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Repositionnement Directory-first : une fiche (structure ou professionnel
 * indépendant, cf. "categorie_fiche") a désormais deux statuts orthogonaux
 * — vérification et publication — là où seuls "actif"/"visible_annuaire"
 * existaient. Ces deux colonnes sont conservées (lues par l'API Directory
 * historique) ; "statut_publication" devient la source de vérité pour la
 * visibilité publique, calculée ci-dessous à partir de leur valeur actuelle
 * pour ne rien changer au comportement des fiches déjà publiées.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cabinets', function (Blueprint $table) {
            $table->string('categorie_fiche')->default('structure')->after('type');
            $table->string('statut_verification')->default('non_verifiee')->after('categorie_fiche');
            $table->string('statut_publication')->default('brouillon')->after('statut_verification');
        });

        DB::statement("ALTER TABLE cabinets ADD CONSTRAINT cabinets_categorie_fiche_check CHECK (categorie_fiche IN ('structure','professionnel_independant'))");
        DB::statement("ALTER TABLE cabinets ADD CONSTRAINT cabinets_statut_verification_check CHECK (statut_verification IN ('non_verifiee','en_attente_verification','verifiee'))");
        DB::statement("ALTER TABLE cabinets ADD CONSTRAINT cabinets_statut_publication_check CHECK (statut_publication IN ('brouillon','publiee','suspendue','archivee'))");

        DB::table('cabinets')->where('actif', true)->where('visible_annuaire', true)->update([
            'statut_verification' => 'verifiee',
            'statut_publication' => 'publiee',
        ]);

        Schema::table('cabinets', function (Blueprint $table) {
            $table->index(['statut_publication', 'categorie_fiche']);
        });
    }

    public function down(): void
    {
        Schema::table('cabinets', function (Blueprint $table) {
            $table->dropIndex(['statut_publication', 'categorie_fiche']);
            $table->dropColumn(['categorie_fiche', 'statut_verification', 'statut_publication']);
        });
    }
};
