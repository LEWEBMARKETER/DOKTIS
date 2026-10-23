<?php

namespace Tests\Feature\Directory;

use App\Enums\RoleUtilisateur;
use App\Models\Cabinet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mission Partie A.D / Partie I : un module désactivé répond 403 côté
 * serveur, même en tapant directement l'URL — jamais un simple masquage
 * frontend. phpunit.xml active DOKTIS_OFFICE_ENABLED par défaut pour que
 * le reste de la suite exerce réellement le module ; ces tests-ci
 * surchargent explicitement le flag pour vérifier le comportement inverse.
 */
class FeatureFlagsTest extends TestCase
{
    use RefreshDatabase;

    public function test_les_routes_office_repondent_403_quand_le_module_est_desactive(): void
    {
        config(['features.office' => false]);

        $cabinet = Cabinet::factory()->create();
        $admin = User::factory()->create(['cabinet_id' => $cabinet->id, 'role' => RoleUtilisateur::Administrateur]);

        $this->actingAs($admin)->getJson('/api/patients')->assertForbidden();
        $this->actingAs($admin)->getJson('/api/dashboard/stats')->assertForbidden();
        $this->actingAs($admin)->getJson('/api/rendez-vous')->assertForbidden();
    }

    public function test_linscription_libre_dun_cabinet_repond_403_quand_office_est_desactive(): void
    {
        config(['features.office' => false]);

        $this->postJson('/api/auth/register-cabinet', [
            'cabinet_nom' => 'Nouveau cabinet',
            'admin_name' => 'Jean Dupont',
            'admin_email' => 'jean@example.ga',
            'admin_password' => 'motdepasse123',
        ])->assertForbidden();

        $this->assertDatabaseMissing('cabinets', ['nom' => 'Nouveau cabinet']);
    }

    public function test_les_routes_dossier_medical_patient_repondent_403_quand_desactivees(): void
    {
        config(['features.patient_medical' => false]);

        $compte = \App\Models\PatientAccount::factory()->create();

        $this->actingAs($compte, 'patient')->getJson('/api/patient/mes-dossiers')->assertForbidden();
        $this->actingAs($compte, 'patient')->getJson('/api/patient/rendez-vous')->assertForbidden();
    }

    public function test_ladministration_super_admin_et_lannuaire_restent_accessibles_office_desactive(): void
    {
        config(['features.office' => false]);

        $admin = User::factory()->create(['role' => RoleUtilisateur::SuperAdmin]);
        $cabinet = Cabinet::factory()->create(['statut_verification' => 'verifiee', 'statut_publication' => 'publiee']);

        $this->actingAs($admin)->getJson('/api/admin/fiches')->assertOk();
        $this->getJson('/api/directory/cabinets')->assertOk();
        $this->getJson("/api/directory/cabinets/{$cabinet->id}")->assertOk();
    }
}
