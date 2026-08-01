<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Historique des traitements par dent (notation FDI 11-48). L'état
     * courant du schéma dentaire d'un patient est déduit de l'entrée la plus
     * récente pour chaque numéro de dent.
     */
    public function up(): void
    {
        Schema::create('dents_traitements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabinet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('numero_dent');
            $table->string('type_traitement');
            $table->enum('statut', ['sain', 'a_traiter', 'traite', 'absent'])->default('a_traiter');
            $table->foreignId('consultation_id')->nullable()->constrained('consultations')->nullOnDelete();
            $table->foreignId('plan_traitement_id')->nullable()->constrained('plans_traitement')->nullOnDelete();
            $table->foreignId('praticien_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('date_traitement');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['cabinet_id', 'patient_id', 'numero_dent']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dents_traitements');
    }
};
