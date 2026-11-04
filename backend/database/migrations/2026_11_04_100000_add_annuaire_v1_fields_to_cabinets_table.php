<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Annuaire V1 : numéro WhatsApp de prise de rendez-vous, slugs de ville et
 * de quartier pour les pages publiques, et statut de la fiche
 * (créée par DOKTIS sans être revendiquée, en attente de validation, vérifiée).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cabinets', function (Blueprint $table) {
            $table->string('whatsapp', 20)->nullable()->after('telephone');
            $table->string('ville_slug')->nullable()->after('ville');
            $table->string('quartier_slug')->nullable()->after('quartier');
            $table->enum('statut_fiche', ['non_revendiquee', 'en_attente', 'verifiee'])
                ->default('verifiee')->after('visible_annuaire');
            $table->index(['ville_slug', 'quartier_slug']);
        });

        DB::table('cabinets')->orderBy('id')->each(function ($cabinet) {
            DB::table('cabinets')->where('id', $cabinet->id)->update([
                'ville_slug' => $cabinet->ville ? Str::slug($cabinet->ville) : null,
                'quartier_slug' => $cabinet->quartier ? Str::slug($cabinet->quartier) : null,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('cabinets', function (Blueprint $table) {
            $table->dropIndex(['ville_slug', 'quartier_slug']);
            $table->dropColumn(['whatsapp', 'ville_slug', 'quartier_slug', 'statut_fiche']);
        });
    }
};
