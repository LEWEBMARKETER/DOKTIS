<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le marché cible de DOKTIS est le Gabon ; la valeur par défaut héritée du
 * MVP initial ("Cameroun") ne concernait que les nouvelles fiches sans
 * pays renseigné. Ne modifie aucune ligne existante.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cabinets', function (Blueprint $table) {
            $table->string('pays')->default('Gabon')->change();
        });
    }

    public function down(): void
    {
        Schema::table('cabinets', function (Blueprint $table) {
            $table->string('pays')->default('Cameroun')->change();
        });
    }
};
