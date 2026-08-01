<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_nouveau_cabinet_peut_etre_cree_avec_son_administrateur(): void
    {
        $response = $this->postJson('/api/auth/register-cabinet', [
            'cabinet_nom' => 'Cabinet Test',
            'admin_name' => 'Dr Test',
            'admin_email' => 'admin@test.dokta',
            'admin_password' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.role', 'administrateur')
            ->assertJsonStructure(['cabinet', 'user', 'token']);

        $this->assertDatabaseHas('users', [
            'email' => 'admin@test.dokta',
            'role' => 'administrateur',
        ]);
    }

    public function test_un_utilisateur_peut_se_connecter_avec_les_bons_identifiants(): void
    {
        $user = User::factory()->create([
            'email' => 'user@test.dokta',
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'user@test.dokta',
            'password' => 'secret123',
        ]);

        $response->assertOk()->assertJsonStructure(['user', 'token']);
        $this->assertSame($user->id, $response->json('user.id'));
    }

    public function test_la_connexion_echoue_avec_un_mauvais_mot_de_passe(): void
    {
        User::factory()->create([
            'email' => 'user@test.dokta',
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'user@test.dokta',
            'password' => 'mauvais-mot-de-passe',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_un_utilisateur_inactif_ne_peut_pas_se_connecter(): void
    {
        User::factory()->create([
            'email' => 'inactif@test.dokta',
            'password' => Hash::make('secret123'),
            'actif' => false,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'inactif@test.dokta',
            'password' => 'secret123',
        ]);

        $response->assertUnprocessable();
    }
}
