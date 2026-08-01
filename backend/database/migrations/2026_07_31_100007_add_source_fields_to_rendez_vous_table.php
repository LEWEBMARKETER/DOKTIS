<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rendez_vous', function (Blueprint $table) {
            $table->enum('source', ['office', 'patient_app'])->default('office')->after('type');
            $table->foreignId('reprogramme_depuis_id')->nullable()->after('source')
                ->constrained('rendez_vous')->nullOnDelete();
        });

        // Postgres implémente enum() via une contrainte CHECK : on la recrée pour ajouter 'reprogramme'.
        DB::statement('ALTER TABLE rendez_vous DROP CONSTRAINT rendez_vous_statut_check');
        DB::statement("ALTER TABLE rendez_vous ADD CONSTRAINT rendez_vous_statut_check CHECK (statut IN ('planifie','confirme','reprogramme','termine','annule','absent'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE rendez_vous DROP CONSTRAINT rendez_vous_statut_check');
        DB::statement("ALTER TABLE rendez_vous ADD CONSTRAINT rendez_vous_statut_check CHECK (statut IN ('planifie','confirme','termine','annule','absent'))");

        Schema::table('rendez_vous', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reprogramme_depuis_id');
            $table->dropColumn('source');
        });
    }
};
