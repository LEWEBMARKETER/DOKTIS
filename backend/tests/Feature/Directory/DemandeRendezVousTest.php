<?php

namespace Tests\Feature\Directory;

use App\Enums\RoleUtilisateur;
use App\Models\Cabinet;
use App\Models\DemandeRendezVous;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mission Partie F : prise de contact publique sans compte ni donnée
 * médicale ; la plateforme n'annonce jamais de rendez-vous confirmé de
 * son propre chef, seul le partenaire décide.
 */
class DemandeRendezVousTest extends TestCase
{
    use RefreshDatabase;

    private function fichePubliee(): Cabinet
    {
        return Cabinet::factory()->create(['statut_verification' => 'verifiee', 'statut_publication' => 'publiee']);
    }

    public function test_un_visiteur_peut_soumettre_une_demande_de_rendez_vous_sans_compte(): void
    {
        $cabinet = $this->fichePubliee();

        $response = $this->postJson("/api/directory/cabinets/{$cabinet->id}/demandes-rendez-vous", [
            'nom' => 'Ndong', 'prenom' => 'Marie', 'telephone' => '065112233',
            'date_souhaitee' => now()->addDays(2)->toDateString(),
            'creneau_souhaite' => 'matin',
            'motif' => 'Consultation de routine',
        ]);

        $response->assertCreated()->assertJsonPath('statut', 'en_attente');
        $this->assertDatabaseHas('demandes_rendez_vous', ['cabinet_id' => $cabinet->id, 'telephone' => '065112233']);
    }

    public function test_une_demande_sur_une_fiche_non_publiee_est_introuvable(): void
    {
        $cabinet = Cabinet::factory()->create(['statut_publication' => 'brouillon']);

        $this->postJson("/api/directory/cabinets/{$cabinet->id}/demandes-rendez-vous", [
            'nom' => 'X', 'prenom' => 'Y', 'telephone' => '065000000',
            'date_souhaitee' => now()->addDay()->toDateString(),
        ])->assertNotFound();
    }

    public function test_une_date_dans_le_passe_est_rejetee(): void
    {
        $cabinet = $this->fichePubliee();

        $this->postJson("/api/directory/cabinets/{$cabinet->id}/demandes-rendez-vous", [
            'nom' => 'X', 'prenom' => 'Y', 'telephone' => '065000000',
            'date_souhaitee' => now()->subDay()->toDateString(),
        ])->assertUnprocessable();
    }

    public function test_une_demande_en_double_le_meme_jour_pour_le_meme_contact_est_rejetee(): void
    {
        $cabinet = $this->fichePubliee();
        $payload = [
            'nom' => 'X', 'prenom' => 'Y', 'telephone' => '065999999',
            'date_souhaitee' => now()->addDay()->toDateString(),
        ];

        $this->postJson("/api/directory/cabinets/{$cabinet->id}/demandes-rendez-vous", $payload)->assertCreated();
        $this->postJson("/api/directory/cabinets/{$cabinet->id}/demandes-rendez-vous", $payload)->assertUnprocessable();
    }

    public function test_le_partenaire_peut_confirmer_une_demande(): void
    {
        $cabinet = $this->fichePubliee();
        $partenaire = User::factory()->create(['cabinet_id' => $cabinet->id, 'role' => RoleUtilisateur::Partenaire]);
        $demande = DemandeRendezVous::create([
            'cabinet_id' => $cabinet->id, 'nom' => 'X', 'prenom' => 'Y', 'telephone' => '065000000',
            'date_souhaitee' => now()->addDay(),
        ]);

        $this->actingAs($partenaire)->postJson("/api/partenaire/demandes-rendez-vous/{$demande->id}/confirmer")
            ->assertOk()->assertJsonPath('statut', 'confirmee');
    }

    public function test_le_partenaire_peut_refuser_une_demande(): void
    {
        $cabinet = $this->fichePubliee();
        $partenaire = User::factory()->create(['cabinet_id' => $cabinet->id, 'role' => RoleUtilisateur::Partenaire]);
        $demande = DemandeRendezVous::create([
            'cabinet_id' => $cabinet->id, 'nom' => 'X', 'prenom' => 'Y', 'telephone' => '065000000',
            'date_souhaitee' => now()->addDay(),
        ]);

        $this->actingAs($partenaire)->postJson("/api/partenaire/demandes-rendez-vous/{$demande->id}/refuser", [
            'notes_partenaire' => 'Créneau indisponible',
        ])->assertOk()->assertJsonPath('statut', 'refusee');
    }

    public function test_un_partenaire_ne_peut_pas_traiter_la_demande_dun_autre_cabinet(): void
    {
        $cabinetA = $this->fichePubliee();
        $cabinetB = $this->fichePubliee();
        $partenaireA = User::factory()->create(['cabinet_id' => $cabinetA->id, 'role' => RoleUtilisateur::Partenaire]);
        $demandeB = DemandeRendezVous::create([
            'cabinet_id' => $cabinetB->id, 'nom' => 'X', 'prenom' => 'Y', 'telephone' => '065000000',
            'date_souhaitee' => now()->addDay(),
        ]);

        $this->actingAs($partenaireA)->postJson("/api/partenaire/demandes-rendez-vous/{$demandeB->id}/confirmer")
            ->assertNotFound();
    }
}
