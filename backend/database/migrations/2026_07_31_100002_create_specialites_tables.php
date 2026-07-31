<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('specialites', function (Blueprint $table) {
            $table->id();
            $table->string('nom')->unique();
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('cabinet_specialite', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabinet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('specialite_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['cabinet_id', 'specialite_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cabinet_specialite');
        Schema::dropIfExists('specialites');
    }
};
