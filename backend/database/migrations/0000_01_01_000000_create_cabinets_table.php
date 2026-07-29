<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cabinets', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('slug')->unique();
            $table->enum('type', ['dentaire', 'medical', 'mixte'])->default('mixte');
            $table->string('telephone')->nullable();
            $table->string('email')->nullable();
            $table->string('adresse')->nullable();
            $table->string('ville')->nullable();
            $table->string('pays')->default('Cameroun');
            $table->string('logo_path')->nullable();
            $table->string('plan')->default('essai');
            $table->timestamp('essai_expire_at')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cabinets');
    }
};
