<?php

namespace Tests\Feature;

use App\Enums\RoleUtilisateur;
use App\Models\Cabinet;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_utilisateur_ne_voit_que_les_patients_de_son_cabinet(): void
    {
        $cabinetA = Cabinet::factory()->create();
        $cabinetB = Cabinet::factory()->create();

        $userA = User::factory()->create(['cabinet_id' => $cabinetA->id, 'role' => RoleUtilisateur::Administrateur]);
        $userB = User::factory()->create(['cabinet_id' => $cabinetB->id, 'role' => RoleUtilisateur::Administrateur]);

        $patientA = Patient::factory()->create(['cabinet_id' => $cabinetA->id]);
        $patientB = Patient::factory()->create(['cabinet_id' => $cabinetB->id]);

        $response = $this->actingAs($userA)->getJson('/api/patients');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($patientA->id));
        $this->assertFalse($ids->contains($patientB->id));
    }

    public function test_un_utilisateur_ne_peut_pas_acceder_a_un_patient_dun_autre_cabinet_par_id(): void
    {
        $cabinetA = Cabinet::factory()->create();
        $cabinetB = Cabinet::factory()->create();

        $userA = User::factory()->create(['cabinet_id' => $cabinetA->id, 'role' => RoleUtilisateur::Administrateur]);
        $patientB = Patient::factory()->create(['cabinet_id' => $cabinetB->id]);

        $response = $this->actingAs($userA)->getJson("/api/patients/{$patientB->id}");

        $response->assertNotFound();
    }

    public function test_un_nouveau_patient_est_automatiquement_rattache_au_cabinet_du_createur(): void
    {
        $cabinet = Cabinet::factory()->create();
        $user = User::factory()->create(['cabinet_id' => $cabinet->id, 'role' => RoleUtilisateur::Secretaire]);

        $response = $this->actingAs($user)->postJson('/api/patients', [
            'nom' => 'Doe',
            'prenom' => 'Jane',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('patients', [
            'nom' => 'Doe',
            'cabinet_id' => $cabinet->id,
        ]);
    }
}
