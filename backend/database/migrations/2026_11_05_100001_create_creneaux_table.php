<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Créneaux publiés par le praticien sur sa page de réservation. Un créneau
 * est seulement « libre » ou « pris » : aucune donnée patient n'est stockée.
 * Le compteur « demandes » compte les ouvertures de WhatsApp sur ce créneau.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creneaux', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabinet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('praticien_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('debut');
            $table->timestamp('fin');
            $table->enum('statut', ['libre', 'pris'])->default('libre');
            $table->unsignedInteger('demandes')->default(0);
            $table->timestamps();
            $table->index(['cabinet_id', 'debut']);
        });

        Schema::table('cabinet_statistiques_jour', function (Blueprint $table) {
            $table->unsignedInteger('vues_qr')->default(0);
            $table->unsignedInteger('vues_lien')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('cabinet_statistiques_jour', function (Blueprint $table) {
            $table->dropColumn(['vues_qr', 'vues_lien']);
        });

        Schema::dropIfExists('creneaux');
    }
};
