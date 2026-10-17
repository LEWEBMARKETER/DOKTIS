<?php

namespace Tests\Feature;

use App\Enums\RoleUtilisateur;
use App\Models\Cabinet;
use App\Models\Facture;
use App\Models\JournalAction;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JournalAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_consultation_dun_dossier_patient_est_journalisee(): void
    {
        $cabinet = Cabinet::factory()->create();
        $user = User::factory()->create(['cabinet_id' => $cabinet->id, 'role' => RoleUtilisateur::Administrateur]);
        $patient = Patient::factory()->create(['cabinet_id' => $cabinet->id]);

        $this->actingAs($user)->getJson("/api/patients/{$patient->id}")->assertOk();

        $this->assertDatabaseHas('journal_actions', [
            'action' => 'dossier.consulte',
            'sujet_type' => Patient::class,
            'sujet_id' => $patient->id,
            'acteur_id' => $user->id,
        ]);
    }

    public function test_la_modification_dune_facture_est_journalisee(): void
    {
        $cabinet = Cabinet::factory()->create();
        $user = User::factory()->create(['cabinet_id' => $cabinet->id, 'role' => RoleUtilisateur::Administrateur]);
        $patient = Patient::factory()->create(['cabinet_id' => $cabinet->id]);
        $facture = Facture::create([
            'cabinet_id' => $cabinet->id,
            'patient_id' => $patient->id,
            'numero' => 'FAC-TEST-0001',
            'montant_total' => 1000,
            'montant_paye' => 0,
            'statut' => 'envoyee',
            'date_emission' => now(),
        ]);

        $this->actingAs($user)->patchJson("/api/factures/{$facture->id}", ['notes' => 'Relance envoyée'])->assertOk();

        $this->assertDatabaseHas('journal_actions', [
            'action' => 'facture.modifiee',
            'sujet_type' => Facture::class,
            'sujet_id' => $facture->id,
        ]);
    }

    public function test_le_journal_ne_contient_jamais_de_mot_de_passe(): void
    {
        $cabinet = Cabinet::factory()->create();
        $user = User::factory()->create(['cabinet_id' => $cabinet->id, 'role' => RoleUtilisateur::Administrateur]);

        $this->actingAs($user)->postJson('/api/users', [
            'name' => 'Nouvel assistant',
            'email' => 'assistant@test.dokta',
            'role' => 'assistant',
            'password' => 'un-mot-de-passe-secret',
        ])->assertCreated();

        $entrees = JournalAction::all();
        $this->assertGreaterThan(0, $entrees->count());
        foreach ($entrees as $entree) {
            $json = json_encode($entree->donnees);
            $this->assertStringNotContainsString('un-mot-de-passe-secret', $json);
        }
    }

    public function test_le_changement_de_role_dun_utilisateur_est_journalise(): void
    {
        $cabinet = Cabinet::factory()->create();
        $admin = User::factory()->create(['cabinet_id' => $cabinet->id, 'role' => RoleUtilisateur::Administrateur]);
        $membre = User::factory()->create(['cabinet_id' => $cabinet->id, 'role' => RoleUtilisateur::Assistant]);

        $this->actingAs($admin)->patchJson("/api/users/{$membre->id}", ['role' => 'secretaire'])->assertOk();

        $this->assertDatabaseHas('journal_actions', [
            'action' => 'utilisateur.permissions_modifiees',
            'sujet_type' => User::class,
            'sujet_id' => $membre->id,
        ]);
    }
}
