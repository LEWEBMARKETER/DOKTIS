<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabinet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->foreignId('rendez_vous_id')->nullable()->constrained('rendez_vous')->nullOnDelete();
            $table->enum('canal', ['whatsapp', 'sms', 'email']);
            $table->string('type');
            $table->string('destinataire');
            $table->text('contenu')->nullable();
            $table->enum('statut', ['en_attente', 'envoye', 'echec'])->default('en_attente');
            $table->timestamp('envoye_at')->nullable();
            $table->timestamps();

            $table->index(['cabinet_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications_log');
    }
};
