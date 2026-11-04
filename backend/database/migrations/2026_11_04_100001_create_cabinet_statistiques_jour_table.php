<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Compteurs anonymes agrégés par jour : aucune donnée sur le visiteur
 * (ni IP, ni identifiant), seulement des totaux par fiche.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cabinet_statistiques_jour', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabinet_id')->constrained()->cascadeOnDelete();
            $table->date('jour');
            $table->unsignedInteger('vues')->default(0);
            $table->unsignedInteger('clics_whatsapp')->default(0);
            $table->unsignedInteger('clics_telephone')->default(0);
            $table->unique(['cabinet_id', 'jour']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cabinet_statistiques_jour');
    }
};
