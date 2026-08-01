<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rendez_vous', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabinet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('praticien_id')->constrained('users')->cascadeOnDelete();
            $table->string('motif')->nullable();
            $table->enum('type', ['consultation', 'controle', 'urgence', 'soin'])->default('consultation');
            $table->dateTime('debut');
            $table->dateTime('fin');
            $table->enum('statut', ['planifie', 'confirme', 'termine', 'annule', 'absent'])->default('planifie');
            $table->text('notes')->nullable();
            $table->timestamp('rappel_envoye_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['cabinet_id', 'praticien_id', 'debut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rendez_vous');
    }
};
