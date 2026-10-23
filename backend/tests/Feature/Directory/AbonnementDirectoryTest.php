<?php

namespace Tests\Feature\Directory;

use App\Enums\RoleUtilisateur;
use App\Models\Abonnement;
use App\Models\Cabinet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mission Partie G : abonnements annuels Directory, paiements constatés
 * manuellement, calcul de la date d'expiration, pas de suppression de
 * fiche à l'échéance.
 */
class AbonnementDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_super_admin_enregistre_un_abonnement_structure_annuel(): void
    {
        $admin = User::factory()->create(['role' => RoleUtilisateur::SuperAdmin]);
        $cabinet = Cabinet::factory()->create(['categorie_fiche' => 'structure']);

        $response = $this->actingAs($admin)->postJson('/api/super-admin/abonnements', [
            'cabinet_id' => $cabinet->id,
            'produit' => 'directory_structure',
            'plan' => 'DOKTIS Structure',
            'prix' => 100000,
            'cycle_facturation' => 'annuel',
            'date_debut' => now()->toDateString(),
        ]);

        $response->assertCreated()->assertJsonPath('produit', 'directory_structure');
        $this->assertDatabaseHas('abonnements', ['cabinet_id' => $cabinet->id, 'produit' => 'directory_structure', 'prix' => 100000]);
    }

    public function test_un_abonnement_office_cree_sans_produit_reste_retrocompatible(): void
    {
        $admin = User::factory()->create(['role' => RoleUtilisateur::SuperAdmin]);
        $cabinet = Cabinet::factory()->create();

        $response = $this->actingAs($admin)->postJson('/api/super-admin/abonnements', [
            'cabinet_id' => $cabinet->id,
            'plan' => 'pro',
            'prix' => 15000,
            'cycle_facturation' => 'mensuel',
            'date_debut' => now()->toDateString(),
        ]);

        $response->assertCreated()->assertJsonPath('produit', 'office');
    }

    public function test_enregistrer_un_paiement_prolonge_la_date_fin_dun_an_pour_un_cycle_annuel(): void
    {
        $admin = User::factory()->create(['role' => RoleUtilisateur::SuperAdmin]);
        $cabinet = Cabinet::factory()->create();
        $abonnement = Abonnement::create([
            'cabinet_id' => $cabinet->id,
            'produit' => 'directory_professionnel',
            'plan' => 'DOKTIS Professionnel',
            'prix' => 50000,
            'cycle_facturation' => 'annuel',
            'statut' => 'actif',
            'date_debut' => now()->toDateString(),
        ]);

        $response = $this->actingAs($admin)->postJson("/api/super-admin/abonnements/{$abonnement->id}/paiements", [
            'montant' => 50000,
            'mode_paiement' => 'airtel_money',
            'reference' => 'AM-0001',
            'date_paiement' => now()->toDateString(),
        ]);

        $response->assertCreated();
        $abonnement->refresh();
        $this->assertEquals(now()->addYear()->toDateString(), $abonnement->date_fin->toDateString());
        $this->assertEquals('actif', $abonnement->statut);
    }

    public function test_un_abonnement_expire_nentraine_pas_la_suppression_de_la_fiche(): void
    {
        $cabinet = Cabinet::factory()->create(['statut_verification' => 'verifiee', 'statut_publication' => 'publiee']);
        Abonnement::create([
            'cabinet_id' => $cabinet->id,
            'produit' => 'directory_structure',
            'plan' => 'DOKTIS Structure',
            'prix' => 100000,
            'cycle_facturation' => 'annuel',
            'statut' => 'expire',
            'date_debut' => now()->subYear(),
            'date_fin' => now()->subDay(),
        ]);

        // Toujours visible : la politique d'expiration ne dépublie/supprime
        // jamais automatiquement une fiche, seul un administrateur agit.
        $this->getJson("/api/directory/cabinets/{$cabinet->id}")->assertOk();
        $this->assertDatabaseHas('cabinets', ['id' => $cabinet->id]);
    }

    public function test_un_partenaire_consulte_son_abonnement_mais_ne_peut_pas_le_modifier(): void
    {
        $cabinet = Cabinet::factory()->create();
        $partenaire = User::factory()->create(['cabinet_id' => $cabinet->id, 'role' => RoleUtilisateur::Partenaire]);
        Abonnement::create([
            'cabinet_id' => $cabinet->id, 'produit' => 'directory_structure', 'plan' => 'DOKTIS Structure',
            'prix' => 100000, 'cycle_facturation' => 'annuel', 'statut' => 'actif', 'date_debut' => now(),
        ]);

        $this->actingAs($partenaire)->getJson('/api/partenaire/abonnement')->assertOk()->assertJsonPath('produit', 'directory_structure');

        // Aucune route de modification n'existe côté partenaire : la seule
        // tentative possible est d'atteindre la route admin, bloquée par le
        // rôle (cf. FicheAdministrationTest pour le motif détaillé).
        $this->actingAs($partenaire)->postJson('/api/super-admin/abonnements', [
            'cabinet_id' => $cabinet->id, 'produit' => 'directory_structure', 'plan' => 'Triché',
            'prix' => 1, 'cycle_facturation' => 'annuel', 'date_debut' => now()->toDateString(),
        ])->assertForbidden();
    }
}
