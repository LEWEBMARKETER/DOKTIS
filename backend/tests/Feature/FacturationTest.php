<?php

namespace Tests\Feature;

use App\Enums\RoleUtilisateur;
use App\Models\Cabinet;
use App\Models\Facture;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacturationTest extends TestCase
{
    use RefreshDatabase;

    private function cabinetAvecAdmin(): array
    {
        $cabinet = Cabinet::factory()->create();
        $admin = User::factory()->create(['cabinet_id' => $cabinet->id, 'role' => RoleUtilisateur::Administrateur]);

        return [$cabinet, $admin];
    }

    public function test_une_facture_calcule_son_total_a_partir_des_lignes(): void
    {
        [$cabinet, $admin] = $this->cabinetAvecAdmin();
        $patient = Patient::factory()->create(['cabinet_id' => $cabinet->id]);

        $response = $this->actingAs($admin)->postJson('/api/factures', [
            'patient_id' => $patient->id,
            'date_emission' => now()->toDateString(),
            'lignes' => [
                ['designation' => 'Consultation', 'quantite' => 1, 'prix_unitaire' => 15000],
                ['designation' => 'Radio', 'quantite' => 2, 'prix_unitaire' => 5000],
            ],
        ]);

        $response->assertCreated();
        $this->assertEquals(25000, $response->json('montant_total'));
    }

    public function test_un_paiement_echelonne_met_a_jour_le_statut_de_la_facture(): void
    {
        [$cabinet, $admin] = $this->cabinetAvecAdmin();
        $patient = Patient::factory()->create(['cabinet_id' => $cabinet->id]);

        $facture = Facture::create([
            'cabinet_id' => $cabinet->id,
            'patient_id' => $patient->id,
            'numero' => 'FAC-TEST-0001',
            'montant_total' => 20000,
            'date_emission' => now()->toDateString(),
            'statut' => 'envoyee',
        ]);

        $this->actingAs($admin)->postJson("/api/factures/{$facture->id}/paiements", [
            'montant' => 10000,
            'mode_paiement' => 'especes',
            'date_paiement' => now()->toDateString(),
            'echeance_numero' => 1,
        ])->assertCreated();

        $facture->refresh();
        $this->assertEquals('partiellement_payee', $facture->statut);
        $this->assertEquals(10000, $facture->montant_paye);

        $this->actingAs($admin)->postJson("/api/factures/{$facture->id}/paiements", [
            'montant' => 10000,
            'mode_paiement' => 'mobile_money',
            'date_paiement' => now()->toDateString(),
            'echeance_numero' => 2,
        ])->assertCreated();

        $facture->refresh();
        $this->assertEquals('payee', $facture->statut);
        $this->assertEquals(20000, $facture->montant_paye);
    }

    public function test_un_paiement_superieur_au_solde_est_rejete(): void
    {
        [$cabinet, $admin] = $this->cabinetAvecAdmin();
        $patient = Patient::factory()->create(['cabinet_id' => $cabinet->id]);

        $facture = Facture::create([
            'cabinet_id' => $cabinet->id,
            'patient_id' => $patient->id,
            'numero' => 'FAC-TEST-0002',
            'montant_total' => 10000,
            'date_emission' => now()->toDateString(),
            'statut' => 'envoyee',
        ]);

        $response = $this->actingAs($admin)->postJson("/api/factures/{$facture->id}/paiements", [
            'montant' => 15000,
            'mode_paiement' => 'especes',
            'date_paiement' => now()->toDateString(),
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('montant');
        $this->assertEquals(0, $facture->fresh()->montant_paye);
    }
}
