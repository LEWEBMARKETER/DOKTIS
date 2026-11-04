<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Demande d'un praticien pour gérer une fiche créée par l'équipe DOKTIS.
 * Le mot de passe choisi est stocké haché et sert à créer le compte
 * administrateur du cabinet quand le Super Admin approuve la demande.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demandes_revendication', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabinet_id')->constrained()->cascadeOnDelete();
            $table->string('nom');
            $table->string('email');
            $table->string('telephone', 50);
            $table->string('fonction')->nullable();
            $table->string('password');
            $table->text('message')->nullable();
            $table->enum('statut', ['en_attente', 'approuvee', 'rejetee'])->default('en_attente');
            $table->text('motif_rejet')->nullable();
            $table->foreignId('traitee_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('traitee_at')->nullable();
            $table->timestamps();
            $table->index(['statut', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demandes_revendication');
    }
};
