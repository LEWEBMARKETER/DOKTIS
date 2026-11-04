<?php

namespace Tests\Feature;

use App\Enums\RoleUtilisateur;
use App\Models\Cabinet;
use App\Models\Creneau;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CreneauxTest extends TestCase
{
    use RefreshDatabase;

    private Cabinet $cabinet;

    private User $secretaire;

    protected function setUp(): void
    {
        parent::setUp();
        // Mercredi 4 novembre 2026, 07:00 à Libreville.
        Carbon::setTestNow(Carbon::parse('2026-11-04 06:00:00', 'UTC'));

        $this->cabinet = Cabinet::factory()->create(['visible_annuaire' => true, 'whatsapp' => '077123456']);
        $this->secretaire = User::factory()->create(['cabinet_id' => $this->cabinet->id, 'role' => RoleUtilisateur::Secretaire]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function generer(array $donnees = [])
    {
        return $this->actingAs($this->secretaire)->postJson('/api/creneaux/generer', [
            'date_debut' => '2026-11-04',
            'date_fin' => '2026-11-06',
            'jours_semaine' => [3, 4],
            'heure_debut' => '08:00',
            'heure_fin' => '10:00',
            'duree_minutes' => 30,
            ...$donnees,
        ]);
    }

    public function test_la_generation_cree_les_creneaux_des_jours_choisis_en_heure_locale(): void
    {
        // Mercredi et jeudi, 08:00 à 10:00 par 30 min = 4 créneaux par jour.
        $this->generer()->assertCreated()->assertJson(['crees' => 8, 'ignores' => 0]);

        $premier = Creneau::orderBy('debut')->first();
        $this->assertSame('2026-11-04 07:00:00', $premier->debut->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('08:00', $premier->heure_debut);
        $this->assertSame('2026-11-04', $premier->date_locale);
        $this->assertSame($this->cabinet->id, $premier->cabinet_id);
    }

    public function test_les_creneaux_passes_et_en_conflit_sont_ignores(): void
    {
        $this->generer(['heure_debut' => '06:00', 'heure_fin' => '08:00', 'jours_semaine' => [3]])
            // 06:00 et 06:30 sont passés (il est 07:00) ; 07:00 aussi (pas strictement futur).
            ->assertJson(['crees' => 1]);

        $this->generer(['jours_semaine' => [3]])->assertJson(['crees' => 4]);
        $this->generer(['jours_semaine' => [3]])->assertJson(['crees' => 0, 'ignores' => 4]);
    }

    public function test_la_liste_publique_ne_montre_que_les_creneaux_libres_a_venir(): void
    {
        $this->generer();
        $pris = Creneau::orderBy('debut')->first();
        $this->actingAs($this->secretaire)->patchJson("/api/creneaux/{$pris->id}", ['statut' => 'pris'])->assertOk();

        $this->app['auth']->forgetGuards();
        $reponse = $this->getJson("/api/directory/cabinets/{$this->cabinet->slug}/creneaux")->assertOk();

        $this->assertCount(7, $reponse->json());
        $this->assertNotContains($pris->id, collect($reponse->json())->pluck('id'));
        $this->assertSame('08:30', $reponse->json('0.heure_debut'));
        $this->assertArrayNotHasKey('demandes', $reponse->json('0'));
    }

    public function test_un_clic_whatsapp_sur_un_creneau_incremente_ses_demandes(): void
    {
        $this->generer(['jours_semaine' => [3]]);
        $creneau = Creneau::first();

        $this->postJson("/api/directory/cabinets/{$this->cabinet->slug}/evenements", ['type' => 'clic_whatsapp', 'creneau_id' => $creneau->id])
            ->assertNoContent();
        $this->postJson("/api/directory/cabinets/{$this->cabinet->slug}/evenements", ['type' => 'vue', 'source' => 'qr'])
            ->assertNoContent();

        $this->assertSame(1, $creneau->fresh()->demandes);

        $this->actingAs($this->secretaire)->getJson('/api/fiche/statistiques')
            ->assertJsonPath('totaux.clics_whatsapp', 1)
            ->assertJsonPath('totaux.vues', 1)
            ->assertJsonPath('totaux.vues_qr', 1);
    }

    public function test_un_cabinet_ne_peut_pas_modifier_les_creneaux_dun_autre(): void
    {
        $this->generer(['jours_semaine' => [3]]);
        $creneau = Creneau::first();
        $autre = User::factory()->create([
            'cabinet_id' => Cabinet::factory()->create()->id,
            'role' => RoleUtilisateur::Administrateur,
        ]);

        $this->actingAs($autre)->patchJson("/api/creneaux/{$creneau->id}", ['statut' => 'pris'])->assertNotFound();
        $this->actingAs($autre)->deleteJson("/api/creneaux/{$creneau->id}")->assertNotFound();
        $this->actingAs($autre)->getJson('/api/creneaux')->assertOk()->assertJsonCount(0);
        $this->assertSame('libre', $creneau->fresh()->statut);
    }

    public function test_vider_un_jour_supprime_seulement_les_creneaux_libres(): void
    {
        $this->generer();
        $pris = Creneau::orderBy('debut')->first();
        $pris->update(['statut' => 'pris']);

        $this->actingAs($this->secretaire)->deleteJson('/api/creneaux/jour/2026-11-04')
            ->assertOk()->assertJson(['supprimes' => 3]);

        $this->assertNotNull($pris->fresh());
        $this->assertSame(5, Creneau::count());
    }

    public function test_le_praticien_doit_appartenir_au_cabinet(): void
    {
        $etranger = User::factory()->create(['cabinet_id' => Cabinet::factory()->create()->id, 'role' => RoleUtilisateur::Medecin]);

        $this->generer(['praticien_id' => $etranger->id])->assertUnprocessable();
        $this->assertSame(0, Creneau::count());
    }
}
