<?php

namespace Tests\Feature;

use App\Enums\RoleUtilisateur;
use App\Models\Avis;
use App\Models\Cabinet;
use App\Models\Consultation;
use App\Models\Facture;
use App\Models\Ordonnance;
use App\Models\Paiement;
use App\Models\Patient;
use App\Models\PlanTraitement;
use App\Models\RendezVous;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Couvre l'isolation multi-tenant exigée par la mission sur l'ensemble des
 * ressources sensibles : patients (déjà couvert ci-dessus), consultations,
 * rendez-vous, ordonnances, factures, paiements, plans de traitement, avis.
 * Les documents médicaux sont couverts par DocumentSecurityTest, les
 * conversations/messages par MessagerieTest.
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Cabinet, 1: User, 2: Cabinet, 3: Patient} Cabinet A + son admin, Cabinet B + un de ses patients */
    private function deuxCabinets(): array
    {
        $cabinetA = Cabinet::factory()->create();
        $cabinetB = Cabinet::factory()->create();
        $userA = User::factory()->create(['cabinet_id' => $cabinetA->id, 'role' => RoleUtilisateur::Administrateur]);
        $patientB = Patient::factory()->create(['cabinet_id' => $cabinetB->id]);

        return [$cabinetA, $userA, $cabinetB, $patientB];
    }

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

    public function test_un_utilisateur_ne_peut_pas_acceder_a_un_rendez_vous_dun_autre_cabinet(): void
    {
        [, $userA, $cabinetB, $patientB] = $this->deuxCabinets();
        $praticienB = User::factory()->create(['cabinet_id' => $cabinetB->id, 'role' => RoleUtilisateur::Medecin]);
        $rdvB = RendezVous::create([
            'cabinet_id' => $cabinetB->id,
            'patient_id' => $patientB->id,
            'praticien_id' => $praticienB->id,
            'type' => 'consultation',
            'source' => 'office',
            'debut' => now()->addDay(),
            'fin' => now()->addDay()->addMinutes(30),
            'statut' => 'planifie',
        ]);

        $this->actingAs($userA)->getJson("/api/rendez-vous/{$rdvB->id}")->assertNotFound();
    }

    public function test_un_utilisateur_ne_peut_pas_acceder_a_une_consultation_dun_autre_cabinet(): void
    {
        [, $userA, $cabinetB, $patientB] = $this->deuxCabinets();
        $praticienB = User::factory()->create(['cabinet_id' => $cabinetB->id, 'role' => RoleUtilisateur::Medecin]);
        $consultationB = Consultation::create([
            'cabinet_id' => $cabinetB->id,
            'patient_id' => $patientB->id,
            'praticien_id' => $praticienB->id,
            'date_consultation' => now(),
        ]);

        $this->actingAs($userA)->getJson("/api/consultations/{$consultationB->id}")->assertNotFound();
    }

    public function test_un_utilisateur_ne_peut_pas_acceder_a_une_ordonnance_dun_autre_cabinet(): void
    {
        [, $userA, $cabinetB, $patientB] = $this->deuxCabinets();
        $praticienB = User::factory()->create(['cabinet_id' => $cabinetB->id, 'role' => RoleUtilisateur::Medecin]);
        $ordonnanceB = Ordonnance::create([
            'cabinet_id' => $cabinetB->id,
            'patient_id' => $patientB->id,
            'praticien_id' => $praticienB->id,
            'contenu' => 'Confidentiel',
            'date_emission' => now(),
        ]);

        $this->actingAs($userA)->getJson("/api/ordonnances/{$ordonnanceB->id}")->assertNotFound();
    }

    public function test_un_utilisateur_ne_peut_pas_acceder_a_un_plan_de_traitement_dun_autre_cabinet(): void
    {
        [, $userA, $cabinetB, $patientB] = $this->deuxCabinets();
        $praticienB = User::factory()->create(['cabinet_id' => $cabinetB->id, 'role' => RoleUtilisateur::Medecin]);
        $planB = PlanTraitement::create([
            'cabinet_id' => $cabinetB->id,
            'patient_id' => $patientB->id,
            'praticien_id' => $praticienB->id,
            'titre' => 'Confidentiel',
        ]);

        $this->actingAs($userA)->getJson("/api/plans-traitement/{$planB->id}")->assertNotFound();
    }

    public function test_un_utilisateur_ne_peut_pas_acceder_a_une_facture_dun_autre_cabinet(): void
    {
        [, $userA, $cabinetB, $patientB] = $this->deuxCabinets();
        $factureB = Facture::create([
            'cabinet_id' => $cabinetB->id,
            'patient_id' => $patientB->id,
            'numero' => 'FAC-B-0001',
            'montant_total' => 1000,
            'montant_paye' => 0,
            'statut' => 'envoyee',
            'date_emission' => now(),
        ]);

        $this->actingAs($userA)->getJson("/api/factures/{$factureB->id}")->assertNotFound();
    }

    public function test_un_utilisateur_ne_peut_pas_acceder_aux_paiements_dune_facture_dun_autre_cabinet(): void
    {
        [, $userA, $cabinetB, $patientB] = $this->deuxCabinets();
        $factureB = Facture::create([
            'cabinet_id' => $cabinetB->id,
            'patient_id' => $patientB->id,
            'numero' => 'FAC-B-0002',
            'montant_total' => 1000,
            'montant_paye' => 0,
            'statut' => 'envoyee',
            'date_emission' => now(),
        ]);
        Paiement::create([
            'cabinet_id' => $cabinetB->id,
            'facture_id' => $factureB->id,
            'montant' => 500,
            'mode_paiement' => 'especes',
            'date_paiement' => now(),
        ]);

        // La facture elle-même renvoie déjà 404 (liaison de modèle scopée) ;
        // la liste de ses paiements doit se comporter de la même façon.
        $this->actingAs($userA)->getJson("/api/factures/{$factureB->id}/paiements")->assertNotFound();
    }

    public function test_un_utilisateur_ne_peut_pas_repondre_a_lavis_dun_autre_cabinet(): void
    {
        [, $userA, $cabinetB] = $this->deuxCabinets();
        $compte = \App\Models\PatientAccount::factory()->create();
        $avisB = Avis::create([
            'cabinet_id' => $cabinetB->id,
            'patient_account_id' => $compte->id,
            'note' => 4,
        ]);

        $this->actingAs($userA)->patchJson("/api/avis/{$avisB->id}", ['reponse_cabinet' => 'Merci'])->assertNotFound();
    }
}
