<?php

namespace Tests\Feature;

use App\Enums\RoleUtilisateur;
use App\Models\Cabinet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_une_secretaire_ne_peut_pas_creer_de_compte_utilisateur(): void
    {
        $cabinet = Cabinet::factory()->create();
        $secretaire = User::factory()->create(['cabinet_id' => $cabinet->id, 'role' => RoleUtilisateur::Secretaire]);

        $response = $this->actingAs($secretaire)->postJson('/api/users', [
            'name' => 'Nouveau',
            'email' => 'nouveau@test.dokta',
            'role' => 'medecin',
            'password' => 'password123',
        ]);

        $response->assertForbidden();
    }

    public function test_un_administrateur_peut_creer_un_compte_utilisateur(): void
    {
        $cabinet = Cabinet::factory()->create();
        $admin = User::factory()->create(['cabinet_id' => $cabinet->id, 'role' => RoleUtilisateur::Administrateur]);

        $response = $this->actingAs($admin)->postJson('/api/users', [
            'name' => 'Nouveau',
            'email' => 'nouveau@test.dokta',
            'role' => 'medecin',
            'password' => 'password123',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('users', ['email' => 'nouveau@test.dokta', 'cabinet_id' => $cabinet->id]);
    }

    public function test_toute_lequipe_du_cabinet_peut_lister_le_personnel(): void
    {
        $cabinet = Cabinet::factory()->create();
        $assistant = User::factory()->create(['cabinet_id' => $cabinet->id, 'role' => RoleUtilisateur::Assistant]);

        $this->actingAs($assistant)->getJson('/api/users')->assertOk();
    }

    public function test_seul_le_super_admin_accede_a_ladministration_des_cabinets(): void
    {
        $cabinet = Cabinet::factory()->create();
        $admin = User::factory()->create(['cabinet_id' => $cabinet->id, 'role' => RoleUtilisateur::Administrateur]);
        $superAdmin = User::factory()->create(['cabinet_id' => null, 'role' => RoleUtilisateur::SuperAdmin]);

        $this->actingAs($admin)->getJson('/api/cabinets')->assertForbidden();
        $this->actingAs($superAdmin)->getJson('/api/cabinets')->assertOk();
    }
}
