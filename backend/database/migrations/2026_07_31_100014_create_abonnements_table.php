<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abonnements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabinet_id')->constrained()->cascadeOnDelete();
            $table->string('plan');
            $table->decimal('prix', 12, 2);
            $table->enum('cycle_facturation', ['mensuel', 'annuel']);
            $table->enum('statut', ['actif', 'suspendu', 'annule'])->default('actif');
            $table->date('date_debut');
            $table->date('date_fin')->nullable();
            $table->string('mode_paiement')->nullable();
            $table->decimal('taux_commission', 5, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abonnements');
    }
};
