<?php

namespace Tests\Feature;

use App\Enums\RoleUtilisateur;
use App\Models\Cabinet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_les_routes_saas_repondent_404_quand_le_module_est_desactive(): void
    {
        config(['dokta.modules.saas' => false]);
        $admin = User::factory()->create([
            'cabinet_id' => Cabinet::factory()->create()->id,
            'role' => RoleUtilisateur::Administrateur,
        ]);

        $this->actingAs($admin)->getJson('/api/patients')->assertNotFound();
        $this->actingAs($admin)->getJson('/api/factures')->assertNotFound();
        $this->actingAs($admin)->getJson('/api/dashboard/stats')->assertNotFound();
    }

    public function test_lespace_praticien_reste_accessible_sans_le_module_saas(): void
    {
        config(['dokta.modules.saas' => false, 'dokta.modules.patient' => false]);
        $admin = User::factory()->create([
            'cabinet_id' => Cabinet::factory()->create()->id,
            'role' => RoleUtilisateur::Administrateur,
        ]);

        $this->actingAs($admin)->getJson('/api/parametres/cabinet')->assertOk();
        $this->actingAs($admin)->getJson('/api/fiche/statistiques')->assertOk();
        $this->actingAs($admin)->getJson('/api/users')->assertOk();
    }

    public function test_les_routes_patient_repondent_404_quand_le_module_est_desactive(): void
    {
        config(['dokta.modules.patient' => false]);

        $this->postJson('/api/patient/auth/register', [])->assertNotFound();
        $this->postJson('/api/patient/auth/login', [])->assertNotFound();
    }

    public function test_lannuaire_public_ne_depend_daucun_module(): void
    {
        config(['dokta.modules.saas' => false, 'dokta.modules.patient' => false]);

        $this->getJson('/api/directory/cabinets')->assertOk();
        $this->getJson('/api/directory/localites')->assertOk();
    }
}
