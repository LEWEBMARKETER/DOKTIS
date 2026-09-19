<?php

namespace Tests\Feature;

use App\Enums\RoleUtilisateur;
use App\Models\Cabinet;
use App\Models\Patient;
use App\Models\RendezVous;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MvpSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_rendez_vous_ne_peut_pas_referencer_un_patient_dun_autre_cabinet(): void
    {
        $cabinetA = Cabinet::factory()->create();
        $cabinetB = Cabinet::factory()->create();
        $user = User::factory()->create(['cabinet_id' => $cabinetA->id, 'role' => RoleUtilisateur::Administrateur]);
        $praticien = User::factory()->create(['cabinet_id' => $cabinetA->id, 'role' => RoleUtilisateur::Medecin]);
        $patientEtranger = Patient::factory()->create(['cabinet_id' => $cabinetB->id]);

        $this->actingAs($user)->postJson('/api/rendez-vous', [
            'patient_id' => $patientEtranger->id,
            'praticien_id' => $praticien->id,
            'debut' => now()->addDay()->toISOString(),
            'fin' => now()->addDay()->addHour()->toISOString(),
        ])->assertUnprocessable()->assertJsonValidationErrors('patient_id');
    }

    public function test_modifier_un_rendez_vous_ne_peut_pas_creer_un_chevauchement(): void
    {
        $cabinet = Cabinet::factory()->create();
        $user = User::factory()->create(['cabinet_id' => $cabinet->id, 'role' => RoleUtilisateur::Administrateur]);
        $praticien = User::factory()->create(['cabinet_id' => $cabinet->id, 'role' => RoleUtilisateur::Medecin]);
        $patient = Patient::factory()->create(['cabinet_id' => $cabinet->id]);
        $debut = now()->addDay()->startOfHour();

        RendezVous::create([
            'cabinet_id' => $cabinet->id, 'patient_id' => $patient->id, 'praticien_id' => $praticien->id,
            'debut' => $debut, 'fin' => $debut->copy()->addHour(),
        ]);
        $aModifier = RendezVous::create([
            'cabinet_id' => $cabinet->id, 'patient_id' => $patient->id, 'praticien_id' => $praticien->id,
            'debut' => $debut->copy()->addHours(2), 'fin' => $debut->copy()->addHours(3),
        ]);

        $this->actingAs($user)->patchJson("/api/rendez-vous/{$aModifier->id}", [
            'debut' => $debut->copy()->addMinutes(30)->toISOString(),
            'fin' => $debut->copy()->addMinutes(90)->toISOString(),
        ])->assertUnprocessable();
    }

    public function test_un_medecin_ne_peut_pas_acceder_aux_factures(): void
    {
        $cabinet = Cabinet::factory()->create();
        $medecin = User::factory()->create(['cabinet_id' => $cabinet->id, 'role' => RoleUtilisateur::Medecin]);

        $this->actingAs($medecin)->getJson('/api/factures')->assertForbidden();
    }
}
