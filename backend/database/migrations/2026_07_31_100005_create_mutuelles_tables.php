<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mutuelles', function (Blueprint $table) {
            $table->id();
            $table->string('nom')->unique();
            $table->timestamps();
        });

        Schema::create('cabinet_mutuelle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabinet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mutuelle_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['cabinet_id', 'mutuelle_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cabinet_mutuelle');
        Schema::dropIfExists('mutuelles');
    }
};
