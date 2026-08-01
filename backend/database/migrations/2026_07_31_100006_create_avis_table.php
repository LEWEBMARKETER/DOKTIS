<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabinet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_account_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('note');
            $table->text('commentaire')->nullable();
            $table->text('reponse_cabinet')->nullable();
            $table->timestamp('reponse_at')->nullable();
            $table->enum('statut', ['visible', 'masque'])->default('visible');
            $table->timestamps();

            $table->unique(['cabinet_id', 'patient_account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avis');
    }
};
