<?php

namespace Database\Seeders;

use App\Enums\RoleUtilisateur;
use App\Models\Mutuelle;
use App\Models\Specialite;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Données de référence nécessaires en production (sans aucune donnée de
 * démonstration). Idempotent : peut être relancé à chaque déploiement.
 */
class ReferentielSeeder extends Seeder
{
    public const SPECIALITES = [
        'Cabinet dentaire', 'Médecin généraliste', 'Gynécologue', 'Pédiatre',
        'Ophtalmologue', 'Laboratoire', "Centre d'imagerie",
        'Cardiologue', 'Dermatologue', 'ORL', 'Kinésithérapeute', 'Sage-femme',
    ];

    public const ASSUREURS = ['CNAMGS', 'NSIA Assurances', 'Ogar Assurances', 'ASCOMA Gabon'];

    public function run(): void
    {
        foreach (self::SPECIALITES as $nom) {
            Specialite::firstOrCreate(['slug' => Str::slug($nom)], ['nom' => $nom]);
        }

        foreach (self::ASSUREURS as $nom) {
            Mutuelle::firstOrCreate(['nom' => $nom]);
        }

        $this->creerSuperAdmin();
    }

    /**
     * Crée le premier compte Super Admin à partir de DOKTA_SUPERADMIN_EMAIL et
     * DOKTA_SUPERADMIN_PASSWORD, s'ils sont fournis et que le compte n'existe pas.
     * Le mot de passe n'est jamais modifié ensuite par ce seeder.
     */
    private function creerSuperAdmin(): void
    {
        $email = config('dokta.super_admin.email');
        $motDePasse = config('dokta.super_admin.password');

        if (! $email || ! $motDePasse || User::where('email', $email)->exists()) {
            return;
        }

        User::create([
            'cabinet_id' => null,
            'name' => config('dokta.super_admin.nom'),
            'email' => $email,
            'password' => Hash::make($motDePasse),
            'role' => RoleUtilisateur::SuperAdmin,
        ]);

        $this->command?->info("Compte Super Admin créé : {$email}");
    }
}
