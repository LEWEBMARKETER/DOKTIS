<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lien d'activation sécurisé envoyé par un administrateur DOKTIS au
 * responsable d'une fiche après sa création. Jamais de mot de passe ni le
 * jeton en clair en base : seul son hash est stocké, comparable à un jeton
 * de réinitialisation de mot de passe classique. À usage unique et à durée
 * limitée (utilise_at/expire_at).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitations_partenaire', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabinet_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('token_hash');
            $table->foreignId('invite_par_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expire_at');
            $table->timestamp('utilise_at')->nullable();
            $table->timestamps();

            $table->index(['cabinet_id', 'utilise_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations_partenaire');
    }
};
