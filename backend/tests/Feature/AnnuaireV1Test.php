<?php

namespace Tests\Feature;

use App\Enums\RoleUtilisateur;
use App\Models\Cabinet;
use App\Models\Specialite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AnnuaireV1Test extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function cabinetVisible(array $attributs = []): Cabinet
    {
        return Cabinet::factory()->create([
            'visible_annuaire' => true,
            'actif' => true,
            'ville' => 'Libreville',
            'quartier' => 'Nzeng-Ayong',
            ...$attributs,
        ]);
    }

    /** @param array<int, array{0:int,1:?string,2:?string}> $jours */
    private function horaires(Cabinet $cabinet, array $jours): void
    {
        $cabinet->remplacerHoraires(array_map(fn ($j) => [
            'jour_semaine' => $j[0],
            'heure_ouverture' => $j[1],
            'heure_fermeture' => $j[2],
            'ferme' => $j[1] === null,
        ], $jours));
    }

    public function test_les_slugs_de_ville_et_quartier_sont_calcules_automatiquement(): void
    {
        $cabinet = Cabinet::create(['nom' => 'Clinique Les Palmiers', 'ville' => 'Port-Gentil', 'quartier' => 'Grand Village']);

        $this->assertSame('port-gentil', $cabinet->ville_slug);
        $this->assertSame('grand-village', $cabinet->quartier_slug);
        $this->assertSame('clinique-les-palmiers-port-gentil', $cabinet->slug);
        $this->assertSame('Gabon', $cabinet->pays);

        $cabinet->update(['quartier' => 'Château']);
        $this->assertSame('chateau', $cabinet->fresh()->quartier_slug);
    }

    public function test_le_numero_whatsapp_est_normalise_au_format_international(): void
    {
        $this->assertSame('24177123456', $this->cabinetVisible(['whatsapp' => '+241 77 12 34 56'])->fresh()->whatsapp);
        $this->assertSame('24177123456', $this->cabinetVisible(['whatsapp' => '077 12 34 56'])->fresh()->whatsapp);
        $this->assertSame('24166123456', $this->cabinetVisible(['whatsapp' => '00241 66-12-34-56'])->fresh()->whatsapp);
        $this->assertNull($this->cabinetVisible(['whatsapp' => ''])->fresh()->whatsapp);
    }

    public function test_la_fiche_est_accessible_par_slug_sans_champs_internes(): void
    {
        $cabinet = $this->cabinetVisible(['slug' => 'cabinet-etoile-libreville', 'whatsapp' => '24177123456']);

        $reponse = $this->getJson('/api/directory/cabinets/cabinet-etoile-libreville');

        $reponse->assertOk()
            ->assertJsonPath('id', $cabinet->id)
            ->assertJsonPath('whatsapp', '24177123456')
            ->assertJsonPath('ville_slug', 'libreville')
            ->assertJsonMissingPath('plan')
            ->assertJsonMissingPath('essai_expire_at');
    }

    public function test_les_filtres_ville_et_quartier_acceptent_les_slugs(): void
    {
        $ici = $this->cabinetVisible();
        $ailleurs = $this->cabinetVisible(['ville' => 'Franceville', 'quartier' => 'Potos']);

        $ids = collect($this->getJson('/api/directory/cabinets?ville=libreville&quartier=nzeng-ayong')->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($ici->id));
        $this->assertFalse($ids->contains($ailleurs->id));

        $ids = collect($this->getJson('/api/directory/cabinets?ville=franceville')->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($ailleurs->id));
    }

    public function test_le_filtre_ouvert_maintenant_utilise_lheure_locale(): void
    {
        // Mercredi 4 novembre 2026, 09:30 à Libreville (08:30 UTC).
        Carbon::setTestNow(Carbon::parse('2026-11-04 08:30:00', 'UTC'));

        $ouvert = $this->cabinetVisible();
        $this->horaires($ouvert, [[3, '08:00', '17:00']]);

        $ferme = $this->cabinetVisible();
        $this->horaires($ferme, [[3, '10:00', '18:00']]);

        $sansHoraires = $this->cabinetVisible();

        $reponse = $this->getJson('/api/directory/cabinets?ouvert=1');
        $ids = collect($reponse->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($ouvert->id));
        $this->assertFalse($ids->contains($ferme->id));
        $this->assertFalse($ids->contains($sansHoraires->id));

        $this->getJson("/api/directory/cabinets/{$ouvert->id}")->assertJsonPath('ouvert_maintenant', true);
        $this->getJson("/api/directory/cabinets/{$ferme->id}")->assertJsonPath('ouvert_maintenant', false);
        $this->getJson("/api/directory/cabinets/{$sansHoraires->id}")->assertJsonPath('ouvert_maintenant', null);
    }

    public function test_les_evenements_incrementent_les_compteurs_du_jour(): void
    {
        $cabinet = $this->cabinetVisible();
        $admin = User::factory()->create(['cabinet_id' => $cabinet->id, 'role' => RoleUtilisateur::Administrateur]);

        $this->postJson("/api/directory/cabinets/{$cabinet->slug}/evenements", ['type' => 'vue'])->assertNoContent();
        $this->postJson("/api/directory/cabinets/{$cabinet->slug}/evenements", ['type' => 'vue'])->assertNoContent();
        $this->postJson("/api/directory/cabinets/{$cabinet->id}/evenements", ['type' => 'clic_whatsapp'])->assertNoContent();
        $this->postJson("/api/directory/cabinets/{$cabinet->id}/evenements", ['type' => 'inconnu'])->assertUnprocessable();

        $this->actingAs($admin)->getJson('/api/fiche/statistiques')
            ->assertOk()
            ->assertJsonPath('totaux.vues', 2)
            ->assertJsonPath('totaux.clics_whatsapp', 1)
            ->assertJsonPath('totaux.clics_telephone', 0);
    }

    public function test_localites_et_plan_du_site_listent_les_pages_non_vides(): void
    {
        $dentiste = Specialite::create(['nom' => 'Dentiste', 'slug' => 'dentiste']);
        $this->cabinetVisible()->specialites()->attach($dentiste);
        $this->cabinetVisible(['quartier' => 'Glass'])->specialites()->attach($dentiste);
        $this->cabinetVisible(['visible_annuaire' => false, 'ville' => 'Lambaréné']);

        $localites = $this->getJson('/api/directory/localites')->assertOk()->json();
        $this->assertCount(1, $localites);
        $this->assertSame('libreville', $localites[0]['slug']);
        $this->assertSame(2, $localites[0]['total']);
        $this->assertCount(2, $localites[0]['quartiers']);

        $plan = $this->getJson('/api/directory/plan-du-site')->assertOk()->json();
        $this->assertCount(2, $plan['cabinets']);
        $pages = collect($plan['pages']);
        $this->assertTrue($pages->contains(fn ($p) => $p['specialite'] === 'dentiste' && $p['ville'] === 'libreville' && $p['quartier'] === null && $p['total'] === 2));
        $this->assertTrue($pages->contains(fn ($p) => $p['quartier'] === 'glass' && $p['total'] === 1));
    }

    public function test_une_inscription_spontanee_reste_invisible_jusqua_validation(): void
    {
        $this->postJson('/api/auth/register-cabinet', [
            'cabinet_nom' => 'Cabinet Nouveau',
            'cabinet_ville' => 'Libreville',
            'cabinet_whatsapp' => '066 00 00 00',
            'admin_name' => 'Dr Nouveau',
            'admin_email' => 'nouveau@test.dokta',
            'admin_password' => 'password123',
        ])->assertCreated()->assertJsonPath('cabinet.statut_fiche', 'en_attente');

        $cabinet = Cabinet::where('nom', 'Cabinet Nouveau')->firstOrFail();
        $this->assertFalse($cabinet->visible_annuaire);
        $this->assertSame('24166000000', $cabinet->whatsapp);
        $this->getJson("/api/directory/cabinets/{$cabinet->slug}")->assertNotFound();
    }
}
