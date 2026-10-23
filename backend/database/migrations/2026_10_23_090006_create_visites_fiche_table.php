<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Statistiques de visibilité par fiche, collectées dans le respect de la
 * vie privée (cf. mission Partie E) : uniquement des compteurs agrégés
 * par jour, jamais d'adresse IP complète ni de diagnostic/contenu médical.
 *
 * "visites_fiche" porte les compteurs agrégés exploités par le tableau de
 * bord partenaire. "visites_fiche_empreintes" ne sert qu'à dédupliquer les
 * visiteurs uniques du jour (empreinte hachée, non réversible, purgée
 * périodiquement — voir App\Console\Commands\PurgerEmpreintesVisites) et
 * n'est jamais exposée à un partenaire.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visites_fiche', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabinet_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('visites')->default(0);
            $table->unsignedInteger('visiteurs_uniques')->default(0);
            $table->unsignedInteger('clics_telephone')->default(0);
            $table->unsignedInteger('clics_whatsapp')->default(0);
            $table->unsignedInteger('clics_itineraire')->default(0);
            $table->timestamps();

            $table->unique(['cabinet_id', 'date']);
        });

        Schema::create('visites_fiche_empreintes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabinet_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('empreinte', 64);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['cabinet_id', 'date', 'empreinte']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visites_fiche_empreintes');
        Schema::dropIfExists('visites_fiche');
    }
};
