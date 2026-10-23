<?php

namespace Tests\Feature\Directory;

use App\Enums\RoleUtilisateur;
use App\Models\Cabinet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Couvre la mission Partie C : seul un administrateur DOKTIS (super_admin)
 * peut créer, vérifier, publier, suspendre ou archiver une fiche.
 */
class FicheAdministrationTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::factory()->create(['role' => RoleUtilisateur::SuperAdmin]);
    }

    public function test_un_super_admin_peut_creer_une_fiche_structure(): void
    {
        $response = $this->actingAs($this->superAdmin())->postJson('/api/admin/fiches', [
            'nom' => 'Clinique Les Palmiers',
            'categorie_fiche' => 'structure',
            'ville' => 'Libreville',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('cabinets', [
            'nom' => 'Clinique Les Palmiers',
            'categorie_fiche' => 'structure',
            'statut_verification' => 'non_verifiee',
            'statut_publication' => 'brouillon',
            'pays' => 'Gabon',
        ]);
    }

    public function test_un_super_admin_peut_creer_une_fiche_professionnel_independant(): void
    {
        $response = $this->actingAs($this->superAdmin())->postJson('/api/admin/fiches', [
            'nom' => 'Dr Awono',
            'categorie_fiche' => 'professionnel_independant',
        ]);

        $response->assertCreated()->assertJsonPath('categorie_fiche', 'professionnel_independant');
    }

    public function test_un_administrateur_de_cabinet_ne_peut_pas_creer_de_fiche(): void
    {
        $cabinet = Cabinet::factory()->create();
        $admin = User::factory()->create(['cabinet_id' => $cabinet->id, 'role' => RoleUtilisateur::Administrateur]);

        $this->actingAs($admin)->postJson('/api/admin/fiches', [
            'nom' => 'Tentative non autorisée',
            'categorie_fiche' => 'structure',
        ])->assertForbidden();
    }

    public function test_un_partenaire_ne_peut_ni_creer_ni_modifier_une_fiche(): void
    {
        $cabinet = Cabinet::factory()->create();
        $partenaire = User::factory()->create(['cabinet_id' => $cabinet->id, 'role' => RoleUtilisateur::Partenaire]);

        $this->actingAs($partenaire)->postJson('/api/admin/fiches', [
            'nom' => 'Tentative non autorisée',
            'categorie_fiche' => 'structure',
        ])->assertForbidden();

        $this->actingAs($partenaire)->patchJson("/api/admin/fiches/{$cabinet->id}", [
            'description' => 'Je me modifie moi-même',
        ])->assertForbidden();
    }

    public function test_une_fiche_doit_etre_verifiee_avant_publication(): void
    {
        $admin = $this->superAdmin();
        $cabinet = Cabinet::factory()->create(['statut_verification' => 'non_verifiee', 'statut_publication' => 'brouillon']);

        $this->actingAs($admin)->postJson("/api/admin/fiches/{$cabinet->id}/publier")->assertUnprocessable();

        $this->actingAs($admin)->postJson("/api/admin/fiches/{$cabinet->id}/verifier")->assertOk();
        $this->actingAs($admin)->postJson("/api/admin/fiches/{$cabinet->id}/publier")->assertOk()
            ->assertJsonPath('statut_publication', 'publiee');

        $this->getJson("/api/directory/cabinets/{$cabinet->id}")->assertOk();
    }

    public function test_suspendre_puis_archiver_une_fiche_la_retire_de_lannuaire_sans_supprimer_les_donnees(): void
    {
        $admin = $this->superAdmin();
        $cabinet = Cabinet::factory()->create(['statut_verification' => 'verifiee', 'statut_publication' => 'publiee']);

        $this->getJson("/api/directory/cabinets/{$cabinet->id}")->assertOk();

        $this->actingAs($admin)->postJson("/api/admin/fiches/{$cabinet->id}/suspendre", ['motif' => 'Vérification complémentaire'])
            ->assertOk();
        $this->getJson("/api/directory/cabinets/{$cabinet->id}")->assertNotFound();
        $this->assertDatabaseHas('cabinets', ['id' => $cabinet->id]);

        $this->actingAs($admin)->postJson("/api/admin/fiches/{$cabinet->id}/archiver")->assertOk();
        $this->getJson("/api/directory/cabinets/{$cabinet->id}")->assertNotFound();
        $this->assertDatabaseHas('cabinets', ['id' => $cabinet->id, 'statut_publication' => 'archivee']);
    }
}
