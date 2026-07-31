<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cabinet_horaires', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabinet_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('jour_semaine'); // 0 = dimanche ... 6 = samedi
            $table->time('heure_ouverture')->nullable();
            $table->time('heure_fermeture')->nullable();
            $table->boolean('ferme')->default(false);
            $table->timestamps();

            $table->unique(['cabinet_id', 'jour_semaine']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cabinet_horaires');
    }
};
