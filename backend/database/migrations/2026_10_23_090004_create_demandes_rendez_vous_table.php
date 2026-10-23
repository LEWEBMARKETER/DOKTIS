<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Demande de contact/rendez-vous déposée depuis l'annuaire public.
 * Volontairement séparée de "rendez_vous" (agenda médical confirmé,
 * contrainte d'exclusion PostgreSQL, dossier patient obligatoire) : ici,
 * aucun compte n'est requis, aucune donnée médicale n'est collectée, et
 * c'est le partenaire qui confirme/refuse manuellement — jamais la
 * plateforme qui annonce un rendez-vous "confirmé" de son propre chef.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demandes_rendez_vous', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabinet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('praticien_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nom');
            $table->string('prenom');
            $table->string('telephone');
            $table->string('email')->nullable();
            $table->date('date_souhaitee');
            $table->string('creneau_souhaite')->nullable();
            $table->string('motif')->nullable();
            $table->string('statut')->default('en_attente');
            $table->date('date_proposee')->nullable();
            $table->string('creneau_propose')->nullable();
            $table->text('notes_partenaire')->nullable();
            $table->timestamp('traite_at')->nullable();
            $table->timestamps();

            $table->index(['cabinet_id', 'statut']);
        });

        \Illuminate\Support\Facades\DB::statement(
            "ALTER TABLE demandes_rendez_vous ADD CONSTRAINT demandes_rendez_vous_statut_check CHECK (statut IN ('en_attente','confirmee','refusee','annulee','terminee'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('demandes_rendez_vous');
    }
};
