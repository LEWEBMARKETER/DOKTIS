<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans_traitement', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabinet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('consultation_id')->nullable()->constrained('consultations')->nullOnDelete();
            $table->foreignId('praticien_id')->constrained('users')->cascadeOnDelete();
            $table->string('titre');
            $table->text('description')->nullable();
            $table->enum('statut', ['propose', 'en_cours', 'termine', 'abandonne'])->default('propose');
            $table->decimal('cout_estime', 12, 2)->nullable();
            $table->date('date_debut')->nullable();
            $table->date('date_fin_prevue')->nullable();
            $table->timestamps();
        });

        Schema::create('plan_traitement_etapes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_traitement_id')->constrained('plans_traitement')->cascadeOnDelete();
            $table->string('titre');
            $table->text('description')->nullable();
            $table->unsignedInteger('ordre')->default(0);
            $table->enum('statut', ['a_faire', 'en_cours', 'realisee', 'annulee'])->default('a_faire');
            $table->decimal('cout', 12, 2)->nullable();
            $table->date('date_prevue')->nullable();
            $table->date('date_realisee')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_traitement_etapes');
        Schema::dropIfExists('plans_traitement');
    }
};
