<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cabinets', function (Blueprint $table) {
            $table->string('quartier')->nullable()->after('ville');
            $table->decimal('latitude', 10, 7)->nullable()->after('quartier');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->text('description')->nullable()->after('longitude');
            $table->json('langues_parlees')->nullable()->after('description');
            $table->boolean('accepte_urgences')->default(false)->after('langues_parlees');
            $table->boolean('accessible_pmr')->default(false)->after('accepte_urgences');
            $table->string('site_web')->nullable()->after('accessible_pmr');
            $table->string('facebook_url')->nullable()->after('site_web');
            $table->string('instagram_url')->nullable()->after('facebook_url');
            $table->decimal('note_moyenne', 3, 2)->default(0)->after('instagram_url');
            $table->unsignedInteger('nombre_avis')->default(0)->after('note_moyenne');
            $table->boolean('visible_annuaire')->default(true)->after('nombre_avis');
        });
    }

    public function down(): void
    {
        Schema::table('cabinets', function (Blueprint $table) {
            $table->dropColumn([
                'quartier', 'latitude', 'longitude', 'description', 'langues_parlees',
                'accepte_urgences', 'accessible_pmr', 'site_web', 'facebook_url',
                'instagram_url', 'note_moyenne', 'nombre_avis', 'visible_annuaire',
            ]);
        });
    }
};
