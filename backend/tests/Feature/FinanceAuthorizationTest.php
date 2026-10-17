<?php

namespace Tests\Feature;

use App\Enums\RoleUtilisateur;
use App\Models\Cabinet;
use App\Models\Facture;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_une_secretaire_ne_peut_pas_supprimer_une_facture(): void
    {
        $cabinet = Cabinet::factory()->create();
        $secretaire = User::factory()->create(['cabinet_id' => $cabinet->id, 'role' => RoleUtilisateur::Secretaire]);
        $patient = Patient::factory()->create(['cabinet_id' => $cabinet->id]);
        $facture = Facture::create([
            'cabinet_id' => $cabinet->id,
            'patient_id' => $patient->id,
            'numero' => 'FAC-TEST-0002',
            'montant_total' => 1000,
            'montant_paye' => 0,
            'statut' => 'envoyee',
            'date_emission' => now(),
        ]);

        $this->actingAs($secretaire)->deleteJson("/api/factures/{$facture->id}")->assertForbidden();
        $this->assertDatabaseHas('factures', ['id' => $facture->id]);
    }

    public function test_un_administrateur_peut_supprimer_une_facture(): void
    {
        $cabinet = Cabinet::factory()->create();
        $admin = User::factory()->create(['cabinet_id' => $cabinet->id, 'role' => RoleUtilisateur::Administrateur]);
        $patient = Patient::factory()->create(['cabinet_id' => $cabinet->id]);
        $facture = Facture::create([
            'cabinet_id' => $cabinet->id,
            'patient_id' => $patient->id,
            'numero' => 'FAC-TEST-0003',
            'montant_total' => 1000,
            'montant_paye' => 0,
            'statut' => 'envoyee',
            'date_emission' => now(),
        ]);

        $this->actingAs($admin)->deleteJson("/api/factures/{$facture->id}")->assertNoContent();
        $this->assertDatabaseMissing('factures', ['id' => $facture->id]);
    }

    public function test_une_secretaire_peut_toujours_creer_et_consulter_des_factures(): void
    {
        $cabinet = Cabinet::factory()->create();
        $secretaire = User::factory()->create(['cabinet_id' => $cabinet->id, 'role' => RoleUtilisateur::Secretaire]);
        $patient = Patient::factory()->create(['cabinet_id' => $cabinet->id]);

        $this->actingAs($secretaire)->postJson('/api/factures', [
            'patient_id' => $patient->id,
            'date_emission' => now()->toDateString(),
            'lignes' => [['designation' => 'Consultation', 'quantite' => 1, 'prix_unitaire' => 10000]],
        ])->assertCreated();
    }
}
