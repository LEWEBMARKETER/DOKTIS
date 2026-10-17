<?php

namespace Tests\Feature;

use App\Enums\RoleUtilisateur;
use App\Models\Cabinet;
use App\Models\Document;
use App\Models\Patient;
use App\Models\PatientAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function creerDocument(Patient $patient, ?int $uploadedBy = null): Document
    {
        $chemin = "patients/{$patient->id}/radio-test.pdf";
        Storage::disk('local')->put($chemin, 'contenu-medical-confidentiel');

        return Document::create([
            'cabinet_id' => $patient->cabinet_id,
            'patient_id' => $patient->id,
            'type' => 'pdf',
            'titre' => 'Radio panoramique',
            'nom_fichier' => 'radio-test.pdf',
            'chemin' => $chemin,
            'disque' => 'local',
            'taille' => 123,
            'mime_type' => 'application/pdf',
            'uploaded_by' => $uploadedBy,
        ]);
    }

    public function test_un_document_nest_jamais_renvoye_avec_une_url_de_stockage_directe(): void
    {
        $cabinet = Cabinet::factory()->create();
        $user = User::factory()->create(['cabinet_id' => $cabinet->id, 'role' => RoleUtilisateur::Administrateur]);
        $patient = Patient::factory()->create(['cabinet_id' => $cabinet->id]);
        $document = $this->creerDocument($patient);

        $response = $this->actingAs($user)->getJson('/api/documents?patient_id='.$patient->id);

        $response->assertOk();
        $body = $response->json();
        $this->assertArrayNotHasKey('url', $body[0]);
        $this->assertStringContainsString('/telecharger', $body[0]['download_url']);
    }

    public function test_le_personnel_du_cabinet_peut_televerser_et_telecharger_un_document(): void
    {
        $cabinet = Cabinet::factory()->create();
        $user = User::factory()->create(['cabinet_id' => $cabinet->id, 'role' => RoleUtilisateur::Administrateur]);
        $patient = Patient::factory()->create(['cabinet_id' => $cabinet->id]);

        $upload = $this->actingAs($user)->postJson('/api/documents', [
            'patient_id' => $patient->id,
            'type' => 'pdf',
            'fichier' => UploadedFile::fake()->create('ordonnance.pdf', 50, 'application/pdf'),
        ]);
        $upload->assertCreated();
        $documentId = $upload->json('id');

        $download = $this->actingAs($user)->get("/api/documents/{$documentId}/telecharger");
        $download->assertOk();
    }

    public function test_le_personnel_dun_autre_cabinet_ne_peut_pas_telecharger_le_document(): void
    {
        $cabinetA = Cabinet::factory()->create();
        $cabinetB = Cabinet::factory()->create();
        $userB = User::factory()->create(['cabinet_id' => $cabinetB->id, 'role' => RoleUtilisateur::Administrateur]);
        $patientA = Patient::factory()->create(['cabinet_id' => $cabinetA->id]);
        $document = $this->creerDocument($patientA);

        $this->actingAs($userB)->get("/api/documents/{$document->id}/telecharger")->assertNotFound();
    }

    public function test_le_telechargement_dun_document_echoue_sans_authentification(): void
    {
        $cabinet = Cabinet::factory()->create();
        $patient = Patient::factory()->create(['cabinet_id' => $cabinet->id]);
        $document = $this->creerDocument($patient);

        $this->getJson("/api/documents/{$document->id}/telecharger")->assertUnauthorized();
    }

    public function test_un_patient_peut_telecharger_son_propre_document(): void
    {
        $cabinet = Cabinet::factory()->create();
        $compte = PatientAccount::factory()->create();
        $patient = Patient::factory()->create(['cabinet_id' => $cabinet->id, 'patient_account_id' => $compte->id]);
        $document = $this->creerDocument($patient);

        $this->actingAs($compte, 'patient')
            ->get("/api/patient/documents/{$document->id}/telecharger")
            ->assertOk();
    }

    public function test_un_patient_ne_peut_pas_telecharger_le_document_dun_autre_patient(): void
    {
        $cabinet = Cabinet::factory()->create();
        $compteA = PatientAccount::factory()->create();
        $compteB = PatientAccount::factory()->create();
        $patientB = Patient::factory()->create(['cabinet_id' => $cabinet->id, 'patient_account_id' => $compteB->id]);
        $document = $this->creerDocument($patientB);

        $this->actingAs($compteA, 'patient')
            ->get("/api/patient/documents/{$document->id}/telecharger")
            ->assertNotFound();
    }

    public function test_la_suppression_dun_document_retire_le_fichier_du_disque(): void
    {
        $cabinet = Cabinet::factory()->create();
        $user = User::factory()->create(['cabinet_id' => $cabinet->id, 'role' => RoleUtilisateur::Administrateur]);
        $patient = Patient::factory()->create(['cabinet_id' => $cabinet->id]);
        $document = $this->creerDocument($patient);

        Storage::disk('local')->assertExists($document->chemin);

        $this->actingAs($user)->deleteJson("/api/documents/{$document->id}")->assertNoContent();

        Storage::disk('local')->assertMissing($document->chemin);
        $this->assertDatabaseMissing('documents', ['id' => $document->id]);
    }
}
