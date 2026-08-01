<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Journal d'audit immuable (pas de updated_at) : qui a fait quoi, sur
     * quel enregistrement, utilisé par le Super Admin DOKTA.
     */
    public function up(): void
    {
        Schema::create('journal_actions', function (Blueprint $table) {
            $table->id();
            $table->string('acteur_type')->nullable();
            $table->unsignedBigInteger('acteur_id')->nullable();
            $table->string('action');
            $table->string('sujet_type')->nullable();
            $table->unsignedBigInteger('sujet_id')->nullable();
            $table->json('donnees')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['sujet_type', 'sujet_id']);
            $table->index(['acteur_type', 'acteur_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_actions');
    }
};
