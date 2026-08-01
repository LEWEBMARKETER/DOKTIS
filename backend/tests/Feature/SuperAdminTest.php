<?php

namespace Tests\Feature;

use App\Enums\RoleUtilisateur;
use App\Models\Cabinet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_administrateur_de_cabinet_ne_peut_pas_acceder_aux_statistiques_nationales(): void
    {
        $cabinet = Cabinet::factory()->create();
        $admin = User::factory()->create(['cabinet_id' => $cabinet->id, 'role' => RoleUtilisateur::Administrateur]);

        $this->actingAs($admin)->getJson('/api/super-admin/statistiques')->assertForbidden();
    }

    public function test_le_super_admin_voit_les_statistiques_nationales(): void
    {
        Cabinet::factory()->count(3)->create();
        $superAdmin = User::factory()->create(['cabinet_id' => null, 'role' => RoleUtilisateur::SuperAdmin]);

        $response = $this->actingAs($superAdmin)->getJson('/api/super-admin/statistiques');

        $response->assertOk()->assertJsonStructure(['total_cabinets', 'cabinets_actifs', 'total_comptes_patients']);
        $this->assertEquals(3, $response->json('total_cabinets'));
    }

    public function test_le_super_admin_peut_creer_un_abonnement_pour_un_cabinet(): void
    {
        $cabinet = Cabinet::factory()->create();
        $superAdmin = User::factory()->create(['cabinet_id' => null, 'role' => RoleUtilisateur::SuperAdmin]);

        $response = $this->actingAs($superAdmin)->postJson('/api/super-admin/abonnements', [
            'cabinet_id' => $cabinet->id,
            'plan' => 'Pro',
            'prix' => 25000,
            'cycle_facturation' => 'mensuel',
            'date_debut' => now()->toDateString(),
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('abonnements', ['cabinet_id' => $cabinet->id, 'plan' => 'Pro']);
        $this->assertDatabaseHas('journal_actions', ['action' => 'abonnement.cree']);
    }
}
