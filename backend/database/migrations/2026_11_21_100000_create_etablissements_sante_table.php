<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Référentiel national des structures sanitaires (DOKTA Directory) :
 * pharmacies, hôpitaux, cliniques, cabinets médicaux et dentaires,
 * laboratoires, opticiens, kinésithérapeutes...
 *
 * Distinct de `cabinets` (clients SaaS de DOKTA Office) : une fiche peut
 * exister sans compte DOKTIS, puis être rattachée à un cabinet quand la
 * structure revendique sa fiche (`cabinet_id`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('etablissements_sante', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // identifiant stable du fichier source, ex. GA-PHA-8972A3
            $table->string('nom');
            $table->string('slug')->unique();
            $table->string('categorie')->index();
            $table->json('specialites')->nullable();
            $table->string('praticien')->nullable();
            $table->string('adresse')->nullable();
            $table->string('quartier')->nullable();
            $table->string('ville')->index();
            $table->string('province')->nullable()->index();
            $table->string('pays')->default('Gabon');
            $table->string('boite_postale')->nullable();
            $table->string('telephone')->nullable();
            $table->json('telephones')->nullable();
            $table->string('email')->nullable();
            $table->string('site_web')->nullable();
            $table->string('horaires')->nullable();
            $table->boolean('ouvert_24h')->default(false);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('osm_ref')->nullable();
            $table->json('sources')->nullable();
            $table->json('source_urls')->nullable();
            $table->string('statut_verification')->default('a_verifier')->index(); // a_verifier, verifie, ferme
            $table->timestamp('verifie_le')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('cabinet_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('visible_annuaire')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('etablissements_sante');
    }
};
