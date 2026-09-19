<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sequences_facturation', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabinet_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('annee');
            $table->unsignedBigInteger('dernier_numero')->default(0);
            $table->unique(['cabinet_id', 'annee']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sequences_facturation');
    }
};
