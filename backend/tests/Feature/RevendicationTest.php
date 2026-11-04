<?php

namespace Tests\Feature;

use App\Enums\RoleUtilisateur;
use App\Models\Cabinet;
use App\Models\DemandeRevendication;
use App\Models\Specialite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RevendicationTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::factory()->create(['cabinet_id' => null, 'role' => RoleUtilisateur::SuperAdmin]);
    }

    private function demande(Cabinet $cabinet, array $donnees = [])
    {
        return $this->postJson("/api/directory/cabinets/{$cabinet->slug}/revendications", [
            'nom' => 'Dr Ngo Bassa',
            'email' => 'ngo.bassa@test.dokta',
            'telephone' => '690112233',
            'fonction' => 'Chirurgien-dentiste',
            'password' => 'motdepasse123',
            ...$donnees,
        ]);
    }

    public function test_le_super_admin_cree_une_fiche_non_revendiquee_visible_dans_lannuaire(): void
    {
        $specialite = Specialite::create(['nom' => 'Dentiste', 'slug' => 'dentiste']);

        $reponse = $this->actingAs($this->superAdmin())->postJson('/api/cabinets', [
            'nom' => 'Cabinet Dentaire du Littoral',
            'ville' => 'Douala',
            'quartier' => 'Akwa',
            'whatsapp' => '+237 690 11 22 33',
            'specialite_ids' => [$specialite->id],
            'horaires' => [['jour_semaine' => 1, 'heure_ouverture' => '08:00', 'heure_fermeture' => '17:00']],
        ]);

        $reponse->assertCreated()
            ->assertJsonPath('statut_fiche', 'non_revendiquee')
            ->assertJsonPath('slug', 'cabinet-dentaire-du-littoral-douala');

        $this->getJson('/api/directory/cabinets/cabinet-dentaire-du-littoral-douala')
            ->assertOk()
            ->assertJsonPath('statut_fiche', 'non_revendiquee')
            ->assertJsonCount(1, 'horaires');
    }

    public function test_un_praticien_revendique_une_fiche_puis_se_connecte_apres_approbation(): void
    {
        $cabinet = Cabinet::factory()->create(['statut_fiche' => 'non_revendiquee', 'visible_annuaire' => true]);

        $this->demande($cabinet)->assertCreated();
        $demande = DemandeRevendication::firstOrFail();
        $this->assertArrayNotHasKey('password', $demande->toArray());

        $this->actingAs($this->superAdmin())
            ->postJson("/api/super-admin/revendications/{$demande->id}/approuver")
            ->assertOk()
            ->assertJsonPath('user.role', 'administrateur');

        $this->assertSame('verifiee', $cabinet->fresh()->statut_fiche);
        $this->assertSame('approuvee', $demande->fresh()->statut);

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/auth/login', ['email' => 'ngo.bassa@test.dokta', 'password' => 'motdepasse123'])
            ->assertOk()
            ->assertJsonPath('user.cabinet_id', $cabinet->id);
    }

    public function test_une_fiche_deja_verifiee_ne_peut_pas_etre_revendiquee(): void
    {
        $cabinet = Cabinet::factory()->create(['statut_fiche' => 'verifiee', 'visible_annuaire' => true]);

        $this->demande($cabinet)->assertUnprocessable();
        $this->assertSame(0, DemandeRevendication::count());
    }

    public function test_lapprobation_rejette_les_autres_demandes_en_attente(): void
    {
        $cabinet = Cabinet::factory()->create(['statut_fiche' => 'non_revendiquee', 'visible_annuaire' => true]);
        $this->demande($cabinet)->assertCreated();
        $this->demande($cabinet, ['email' => 'autre@test.dokta'])->assertCreated();

        [$premiere, $seconde] = DemandeRevendication::orderBy('id')->get();

        $this->actingAs($this->superAdmin())->postJson("/api/super-admin/revendications/{$premiere->id}/approuver")->assertOk();

        $this->assertSame('rejetee', $seconde->fresh()->statut);
    }

    public function test_seul_le_super_admin_traite_les_revendications(): void
    {
        $cabinet = Cabinet::factory()->create(['statut_fiche' => 'non_revendiquee', 'visible_annuaire' => true]);
        $this->demande($cabinet)->assertCreated();
        $admin = User::factory()->create(['cabinet_id' => $cabinet->id, 'role' => RoleUtilisateur::Administrateur]);

        $this->actingAs($admin)->getJson('/api/super-admin/revendications')->assertForbidden();
    }
}
