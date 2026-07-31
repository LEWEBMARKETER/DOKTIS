<?php

namespace Tests\Feature;

use App\Enums\RoleUtilisateur;
use App\Models\Cabinet;
use App\Models\Patient;
use App\Models\PatientAccount;
use App\Models\RendezVous;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientRendezVousTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_patient_peut_prendre_rendez_vous_et_un_dossier_est_cree_automatiquement(): void
    {
        $cabinet = Cabinet::factory()->create();
        $medecin = User::factory()->create(['cabinet_id' => $cabinet->id, 'role' => RoleUtilisateur::Medecin]);
        $account = PatientAccount::factory()->create();

        $response = $this->actingAs($account)->postJson('/api/patient/rendez-vous', [
            'cabinet_id' => $cabinet->id,
            'praticien_id' => $medecin->id,
            'motif' => 'Contrôle',
            'debut' => now()->addDay()->toIso8601String(),
            'fin' => now()->addDay()->addMinutes(30)->toIso8601String(),
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('patients', ['cabinet_id' => $cabinet->id, 'patient_account_id' => $account->id]);
        $this->assertDatabaseHas('rendez_vous', ['cabinet_id' => $cabinet->id, 'source' => 'patient_app', 'statut' => 'planifie']);
    }

    public function test_un_patient_ne_voit_pas_les_rendez_vous_dun_autre_patient(): void
    {
        $cabinet = Cabinet::factory()->create();
        $medecin = User::factory()->create(['cabinet_id' => $cabinet->id, 'role' => RoleUtilisateur::Medecin]);

        $compteA = PatientAccount::factory()->create();
        $compteB = PatientAccount::factory()->create();

        $dossierA = Patient::create(['cabinet_id' => $cabinet->id, 'patient_account_id' => $compteA->id, 'numero_dossier' => 'A-1', 'nom' => 'A', 'prenom' => 'A']);
        $dossierB = Patient::create(['cabinet_id' => $cabinet->id, 'patient_account_id' => $compteB->id, 'numero_dossier' => 'B-1', 'nom' => 'B', 'prenom' => 'B']);

        RendezVous::create(['cabinet_id' => $cabinet->id, 'patient_id' => $dossierA->id, 'praticien_id' => $medecin->id, 'debut' => now()->addDay(), 'fin' => now()->addDay()->addMinutes(30), 'statut' => 'planifie']);
        RendezVous::create(['cabinet_id' => $cabinet->id, 'patient_id' => $dossierB->id, 'praticien_id' => $medecin->id, 'debut' => now()->addDays(2), 'fin' => now()->addDays(2)->addMinutes(30), 'statut' => 'planifie']);

        $response = $this->actingAs($compteA)->getJson('/api/patient/rendez-vous');

        $response->assertOk();
        $this->assertCount(1, $response->json());
        $this->assertEquals($dossierA->id, $response->json('0.patient_id'));
    }

    public function test_un_patient_ne_peut_pas_annuler_le_rendez_vous_dun_autre_patient(): void
    {
        $cabinet = Cabinet::factory()->create();
        $medecin = User::factory()->create(['cabinet_id' => $cabinet->id, 'role' => RoleUtilisateur::Medecin]);

        $compteA = PatientAccount::factory()->create();
        $compteB = PatientAccount::factory()->create();
        $dossierB = Patient::create(['cabinet_id' => $cabinet->id, 'patient_account_id' => $compteB->id, 'numero_dossier' => 'B-1', 'nom' => 'B', 'prenom' => 'B']);
        $rdvB = RendezVous::create(['cabinet_id' => $cabinet->id, 'patient_id' => $dossierB->id, 'praticien_id' => $medecin->id, 'debut' => now()->addDay(), 'fin' => now()->addDay()->addMinutes(30), 'statut' => 'planifie']);

        $this->actingAs($compteA)->postJson("/api/patient/rendez-vous/{$rdvB->id}/annuler")->assertNotFound();
    }
}
