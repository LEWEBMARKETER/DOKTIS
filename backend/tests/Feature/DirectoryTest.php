<?php

namespace Tests\Feature;

use App\Models\Cabinet;
use App\Models\Specialite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_lannuaire_ne_liste_que_les_cabinets_visibles_et_actifs(): void
    {
        Cabinet::factory()->create(['visible_annuaire' => true, 'actif' => true, 'nom' => 'Cabinet Visible']);
        Cabinet::factory()->create(['visible_annuaire' => false, 'actif' => true, 'nom' => 'Cabinet Masque']);
        Cabinet::factory()->create(['visible_annuaire' => true, 'actif' => false, 'nom' => 'Cabinet Inactif']);

        $response = $this->getJson('/api/directory/cabinets');

        $response->assertOk();
        $noms = collect($response->json('data'))->pluck('nom');
        $this->assertTrue($noms->contains('Cabinet Visible'));
        $this->assertFalse($noms->contains('Cabinet Masque'));
        $this->assertFalse($noms->contains('Cabinet Inactif'));
    }

    public function test_le_filtre_par_specialite_fonctionne_par_slug(): void
    {
        $dentaire = Specialite::create(['nom' => 'Cabinet dentaire', 'slug' => 'cabinet-dentaire']);
        $generaliste = Specialite::create(['nom' => 'Médecin généraliste', 'slug' => 'medecin-generaliste']);

        $cabinetDentaire = Cabinet::factory()->create(['visible_annuaire' => true, 'actif' => true]);
        $cabinetDentaire->specialites()->attach($dentaire);

        $cabinetGeneraliste = Cabinet::factory()->create(['visible_annuaire' => true, 'actif' => true]);
        $cabinetGeneraliste->specialites()->attach($generaliste);

        $response = $this->getJson('/api/directory/cabinets?specialite=cabinet-dentaire');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($cabinetDentaire->id));
        $this->assertFalse($ids->contains($cabinetGeneraliste->id));
    }

    public function test_la_fiche_dun_cabinet_masque_est_introuvable(): void
    {
        $cabinet = Cabinet::factory()->create(['visible_annuaire' => false]);

        $this->getJson("/api/directory/cabinets/{$cabinet->id}")->assertNotFound();
    }
}
