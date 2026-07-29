<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ordonnance_modeles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabinet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('praticien_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('titre');
            $table->text('contenu');
            $table->timestamps();
        });

        Schema::create('ordonnances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabinet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('consultation_id')->nullable()->constrained('consultations')->nullOnDelete();
            $table->foreignId('praticien_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('ordonnance_modele_id')->nullable()->constrained('ordonnance_modeles')->nullOnDelete();
            $table->text('contenu');
            $table->date('date_emission');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordonnances');
        Schema::dropIfExists('ordonnance_modeles');
    }
};
