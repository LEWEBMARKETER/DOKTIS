<?php

namespace Tests\Feature;

use App\Enums\RoleUtilisateur;
use App\Models\Cabinet;
use App\Models\Devis;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DevisTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_devis_accepte_peut_etre_converti_en_facture(): void
    {
        $cabinet = Cabinet::factory()->create();
        $admin = User::factory()->create(['cabinet_id' => $cabinet->id, 'role' => RoleUtilisateur::Administrateur]);
        $patient = Patient::factory()->create(['cabinet_id' => $cabinet->id]);

        $devis = Devis::create([
            'cabinet_id' => $cabinet->id,
            'patient_id' => $patient->id,
            'numero' => 'DEV-TEST-0001',
            'montant_total' => 30000,
            'statut' => 'accepte',
            'date_emission' => now()->toDateString(),
        ]);
        $devis->lignes()->create(['designation' => 'Bilan', 'quantite' => 1, 'prix_unitaire' => 30000, 'montant' => 30000]);

        $response = $this->actingAs($admin)->postJson("/api/devis/{$devis->id}/convertir");

        $response->assertCreated();
        $this->assertDatabaseHas('factures', ['patient_id' => $patient->id, 'montant_total' => 30000]);
        $this->assertEquals('converti', $devis->fresh()->statut);
        $this->assertNotNull($devis->fresh()->facture_id);
    }

    public function test_un_devis_deja_converti_ne_peut_pas_etre_reconverti(): void
    {
        $cabinet = Cabinet::factory()->create();
        $admin = User::factory()->create(['cabinet_id' => $cabinet->id, 'role' => RoleUtilisateur::Administrateur]);
        $patient = Patient::factory()->create(['cabinet_id' => $cabinet->id]);

        $devis = Devis::create([
            'cabinet_id' => $cabinet->id,
            'patient_id' => $patient->id,
            'numero' => 'DEV-TEST-0002',
            'montant_total' => 10000,
            'statut' => 'converti',
            'date_emission' => now()->toDateString(),
        ]);

        $this->actingAs($admin)->postJson("/api/devis/{$devis->id}/convertir")->assertUnprocessable();
    }
}
