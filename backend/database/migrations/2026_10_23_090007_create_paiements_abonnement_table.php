<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Historique des paiements d'un abonnement (Office ou Directory), saisis
 * manuellement par un administrateur DOKTIS — aucune intégration de
 * paiement en ligne au lancement (Mobile Money/virement/espèces constatés
 * après réception, jamais simulés comme réussis automatiquement). Sert à
 * la fois de justificatif téléchargeable pour le partenaire et de base de
 * calcul pour les rappels d'échéance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paiements_abonnement', function (Blueprint $table) {
            $table->id();
            $table->foreignId('abonnement_id')->constrained('abonnements')->cascadeOnDelete();
            $table->decimal('montant', 12, 2);
            $table->string('mode_paiement');
            $table->string('reference')->nullable();
            $table->date('date_paiement');
            $table->foreignId('enregistre_par_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        DB::statement(
            "ALTER TABLE paiements_abonnement ADD CONSTRAINT paiements_abonnement_mode_paiement_check CHECK (mode_paiement IN ('airtel_money','moov_money','virement','especes'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('paiements_abonnement');
    }
};
