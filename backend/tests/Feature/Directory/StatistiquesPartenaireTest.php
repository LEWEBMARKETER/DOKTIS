<?php

namespace Tests\Feature\Directory;

use App\Enums\RoleUtilisateur;
use App\Models\Cabinet;
use App\Models\DemandeRendezVous;
use App\Models\User;
use App\Models\VisiteFiche;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mission Partie E : un partenaire consulte les statistiques de sa propre
 * fiche, jamais celles d'un autre.
 */
class StatistiquesPartenaireTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_partenaire_consulte_les_statistiques_de_sa_propre_fiche(): void
    {
        $cabinet = Cabinet::factory()->create();
        $partenaire = User::factory()->create(['cabinet_id' => $cabinet->id, 'role' => RoleUtilisateur::Partenaire]);

        VisiteFiche::create([
            'cabinet_id' => $cabinet->id,
            'date' => now()->toDateString(),
            'visites' => 10,
            'visiteurs_uniques' => 7,
            'clics_telephone' => 2,
            'clics_whatsapp' => 1,
            'clics_itineraire' => 3,
        ]);

        DemandeRendezVous::create([
            'cabinet_id' => $cabinet->id,
            'nom' => 'Mba', 'prenom' => 'Alice', 'telephone' => '066000000',
            'date_souhaitee' => now()->addDay(), 'statut' => 'confirmee',
        ]);

        $response = $this->actingAs($partenaire)->getJson('/api/partenaire/statistiques?periode=30');

        $response->assertOk();
        $response->assertJsonPath('totaux.visites', 10);
        $response->assertJsonPath('totaux.visiteurs_uniques_estimes', 7);
        $response->assertJsonPath('totaux.clics_telephone', 2);
        $response->assertJsonPath('totaux.demandes_rendez_vous', 1);
        $response->assertJsonPath('totaux.demandes_confirmees', 1);
    }

    public function test_un_partenaire_ne_voit_jamais_les_statistiques_dun_autre(): void
    {
        $cabinetA = Cabinet::factory()->create();
        $cabinetB = Cabinet::factory()->create();
        $partenaireA = User::factory()->create(['cabinet_id' => $cabinetA->id, 'role' => RoleUtilisateur::Partenaire]);

        VisiteFiche::create(['cabinet_id' => $cabinetB->id, 'date' => now()->toDateString(), 'visites' => 999]);

        $response = $this->actingAs($partenaireA)->getJson('/api/partenaire/statistiques');

        $response->assertOk();
        $response->assertJsonPath('totaux.visites', 0);
    }

    public function test_les_clics_et_visites_publics_incrementent_les_compteurs_de_la_bonne_fiche(): void
    {
        $cabinet = Cabinet::factory()->create(['statut_verification' => 'verifiee', 'statut_publication' => 'publiee']);
        $partenaire = User::factory()->create(['cabinet_id' => $cabinet->id, 'role' => RoleUtilisateur::Partenaire]);

        $navigateur = $this->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        $navigateur->postJson("/api/directory/cabinets/{$cabinet->id}/visite")->assertNoContent();
        $navigateur->postJson("/api/directory/cabinets/{$cabinet->id}/clic/telephone")->assertNoContent();
        $navigateur->postJson("/api/directory/cabinets/{$cabinet->id}/clic/whatsapp")->assertNoContent();

        $stats = $this->actingAs($partenaire)->getJson('/api/partenaire/statistiques')->json('totaux');

        $this->assertSame(1, $stats['visites']);
        $this->assertSame(1, $stats['clics_telephone']);
        $this->assertSame(1, $stats['clics_whatsapp']);
    }

    public function test_un_robot_connu_nest_pas_compte_comme_une_visite(): void
    {
        $cabinet = Cabinet::factory()->create(['statut_verification' => 'verifiee', 'statut_publication' => 'publiee']);
        $partenaire = User::factory()->create(['cabinet_id' => $cabinet->id, 'role' => RoleUtilisateur::Partenaire]);

        $this->withHeader('User-Agent', 'Mozilla/5.0 (compatible; Googlebot/2.1)')
            ->postJson("/api/directory/cabinets/{$cabinet->id}/visite")
            ->assertNoContent();

        $stats = $this->actingAs($partenaire)->getJson('/api/partenaire/statistiques')->json('totaux');

        $this->assertSame(0, $stats['visites']);
    }
}
