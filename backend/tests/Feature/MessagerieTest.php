<?php

namespace Tests\Feature;

use App\Enums\RoleUtilisateur;
use App\Models\Cabinet;
use App\Models\Conversation;
use App\Models\PatientAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessagerieTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_patient_peut_demarrer_une_conversation_et_envoyer_un_message(): void
    {
        $cabinet = Cabinet::factory()->create();
        $account = PatientAccount::factory()->create();

        $start = $this->actingAs($account)->postJson("/api/patient/cabinets/{$cabinet->id}/conversation");
        $start->assertCreated();
        $conversationId = $start->json('id');

        $response = $this->actingAs($account)->postJson("/api/patient/conversations/{$conversationId}/messages", [
            'contenu' => 'Bonjour, avez-vous un créneau cette semaine ?',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('messages', ['conversation_id' => $conversationId, 'expediteur_type' => 'patient']);
    }

    public function test_un_patient_ne_peut_pas_lire_la_conversation_dun_autre_patient(): void
    {
        $cabinet = Cabinet::factory()->create();
        $compteA = PatientAccount::factory()->create();
        $compteB = PatientAccount::factory()->create();

        $conversation = Conversation::create(['cabinet_id' => $cabinet->id, 'patient_account_id' => $compteA->id]);

        $this->actingAs($compteB)
            ->getJson("/api/patient/conversations/{$conversation->id}/messages")
            ->assertNotFound();
    }

    public function test_le_personnel_dun_autre_cabinet_ne_peut_pas_repondre_a_la_conversation(): void
    {
        $cabinetA = Cabinet::factory()->create();
        $cabinetB = Cabinet::factory()->create();
        $staffB = User::factory()->create(['cabinet_id' => $cabinetB->id, 'role' => RoleUtilisateur::Secretaire]);
        $account = PatientAccount::factory()->create();

        $conversation = Conversation::create(['cabinet_id' => $cabinetA->id, 'patient_account_id' => $account->id]);

        // Le scope cabinet fait échouer la résolution du modèle avant le contrôleur : 404 attendu.
        $this->actingAs($staffB)
            ->postJson("/api/conversations/{$conversation->id}/messages", ['contenu' => 'Test'])
            ->assertNotFound();
    }
}
