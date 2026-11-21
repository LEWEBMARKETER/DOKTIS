<?php

namespace Tests\Feature;

use App\Models\EtablissementSante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnuaireSanteTest extends TestCase
{
    use RefreshDatabase;

    private function cheminCsv(): string
    {
        return base_path('../data/annuaire-gabon/etablissements_sante_gabon.csv');
    }

    public function test_le_fichier_de_lannuaire_sinporte_et_est_idempotent(): void
    {
        $this->artisan('annuaire:importer', ['fichier' => $this->cheminCsv()])->assertSuccessful();
        $total = EtablissementSante::count();
        $this->assertGreaterThan(100, $total);

        $forestiers = EtablissementSante::where('nom', 'Grande Pharmacie des Forestiers')->firstOrFail();
        $this->assertSame('pharmacie', $forestiers->categorie);
        $this->assertSame('Estuaire', $forestiers->province);
        $this->assertContains('+24111722352', $forestiers->telephones);

        // Une vérification faite dans DOKTIS survit à un nouvel import.
        $forestiers->update(['statut_verification' => 'verifie']);
        $this->artisan('annuaire:importer', ['fichier' => $this->cheminCsv()])->assertSuccessful();

        $this->assertSame($total, EtablissementSante::count());
        $this->assertSame('verifie', $forestiers->fresh()->statut_verification);
    }

    public function test_lapi_filtre_par_categorie_et_ville(): void
    {
        $this->artisan('annuaire:importer', ['fichier' => $this->cheminCsv()])->assertSuccessful();

        $reponse = $this->getJson('/api/directory/etablissements?categorie=pharmacie&ville=Port-Gentil&par_page=100');

        $reponse->assertOk();
        $fiches = collect($reponse->json('data'));
        $this->assertNotEmpty($fiches);
        $this->assertTrue($fiches->every(fn ($f) => $f['categorie'] === 'pharmacie' && $f['ville'] === 'Port-Gentil'));
    }

    public function test_les_structures_fermees_ou_masquees_ne_sont_pas_listees(): void
    {
        $base = ['categorie' => 'pharmacie', 'ville' => 'Libreville'];
        EtablissementSante::create($base + ['code' => 'T-1', 'nom' => 'Pharmacie Ouverte', 'slug' => 't-1']);
        EtablissementSante::create($base + ['code' => 'T-2', 'nom' => 'Pharmacie Fermee', 'slug' => 't-2', 'statut_verification' => 'ferme']);
        $masquee = EtablissementSante::create($base + ['code' => 'T-3', 'nom' => 'Pharmacie Masquee', 'slug' => 't-3', 'visible_annuaire' => false]);

        $noms = collect($this->getJson('/api/directory/etablissements')->json('data'))->pluck('nom');

        $this->assertEquals(['Pharmacie Ouverte'], $noms->all());
        $this->getJson("/api/directory/etablissements/{$masquee->id}")->assertNotFound();
    }
}
