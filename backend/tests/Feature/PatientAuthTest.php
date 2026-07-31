<?php

namespace Tests\Feature;

use App\Models\PatientAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_patient_peut_creer_un_compte(): void
    {
        $response = $this->postJson('/api/patient/auth/register', [
            'nom' => 'Kamdem',
            'prenom' => 'Sonia',
            'email' => 'sonia@patient.dokta',
            'telephone' => '690112233',
            'password' => 'password123',
        ]);

        $response->assertCreated()->assertJsonStructure(['account', 'token']);
        $this->assertDatabaseHas('patient_accounts', ['email' => 'sonia@patient.dokta']);
    }

    public function test_un_patient_peut_se_connecter_avec_email_ou_telephone(): void
    {
        PatientAccount::factory()->create(['email' => 'a@patient.dokta', 'telephone' => '690000001']);

        $this->postJson('/api/patient/auth/login', ['identifiant' => 'a@patient.dokta', 'password' => 'password'])
            ->assertOk();

        $this->postJson('/api/patient/auth/login', ['identifiant' => '690000001', 'password' => 'password'])
            ->assertOk();
    }

    public function test_un_jeton_patient_ne_peut_pas_acceder_aux_routes_du_cabinet(): void
    {
        $account = PatientAccount::factory()->create();
        $token = $account->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/patients')
            ->assertForbidden();
    }

    public function test_un_jeton_staff_ne_peut_pas_acceder_aux_routes_patient(): void
    {
        $response = $this->postJson('/api/auth/register-cabinet', [
            'cabinet_nom' => 'Cabinet Test',
            'admin_name' => 'Dr Test',
            'admin_email' => 'admin@test.dokta',
            'admin_password' => 'password123',
        ]);
        $token = $response->json('token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/patient/auth/me')
            ->assertForbidden();
    }
}
