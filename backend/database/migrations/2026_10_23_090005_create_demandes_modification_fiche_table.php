<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Un partenaire ne modifie jamais directement sa fiche publiée : il
 * propose des changements, qu'un administrateur DOKTIS valide ou refuse
 * avant toute republication. Conserve un historique complet des demandes
 * et décisions (jamais supprimé, uniquement archivé via son statut).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demandes_modification_fiche', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabinet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('partenaire_id')->constrained('users')->cascadeOnDelete();
            $table->json('champs_proposes');
            $table->string('statut')->default('en_attente');
            $table->text('commentaire_admin')->nullable();
            $table->foreignId('traite_par_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('traite_at')->nullable();
            $table->timestamps();

            $table->index(['cabinet_id', 'statut']);
        });

        DB::statement(
            "ALTER TABLE demandes_modification_fiche ADD CONSTRAINT demandes_modification_fiche_statut_check CHECK (statut IN ('en_attente','validee','refusee'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('demandes_modification_fiche');
    }
};
