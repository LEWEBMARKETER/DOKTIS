<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabinet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('praticien_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('rendez_vous_id')->nullable()->constrained('rendez_vous')->nullOnDelete();
            $table->dateTime('date_consultation');
            $table->string('motif')->nullable();
            $table->text('diagnostic')->nullable();
            $table->text('observations')->nullable();
            $table->text('traitement')->nullable();
            $table->decimal('poids', 5, 2)->nullable();
            $table->string('tension')->nullable();
            $table->decimal('temperature', 4, 1)->nullable();
            $table->date('prochaine_visite_recommandee')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['cabinet_id', 'patient_id', 'date_consultation']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultations');
    }
};
