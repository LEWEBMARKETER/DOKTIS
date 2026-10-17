<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Sanctum ne démarre la session "stateful" (et donc le cookie HttpOnly)
     * que pour les requêtes dont le Referer/Origin correspond à un domaine
     * listé dans SANCTUM_STATEFUL_DOMAINS — c'est le comportement réel du
     * frontend Next.js (même origine via le proxy de rewrites), reproduit
     * ici pour les tests.
     */
    private function depuisFrontend(): self
    {
        return $this->withHeader('Referer', 'http://localhost:3000/login');
    }

    public function test_un_nouveau_cabinet_peut_etre_cree_avec_son_administrateur(): void
    {
        $response = $this->depuisFrontend()->postJson('/api/auth/register-cabinet', [
            'cabinet_nom' => 'Cabinet Test',
            'admin_name' => 'Dr Test',
            'admin_email' => 'admin@test.dokta',
            'admin_password' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.role', 'administrateur')
            ->assertJsonStructure(['cabinet', 'user'])
            ->assertJsonMissingPath('user.password');

        // Aucun jeton n'est renvoyé : l'authentification repose uniquement
        // sur le cookie de session HttpOnly posé par la réponse.
        $response->assertJsonMissingPath('token');

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

        $response = $this->depuisFrontend()->postJson('/api/auth/login', [
            'email' => 'user@test.dokta',
            'password' => 'secret123',
        ]);

        $response->assertOk()->assertJsonStructure(['user'])->assertJsonMissingPath('token');
        $this->assertSame($user->id, $response->json('user.id'));
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_la_connexion_echoue_avec_un_mauvais_mot_de_passe(): void
    {
        User::factory()->create([
            'email' => 'user@test.dokta',
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->depuisFrontend()->postJson('/api/auth/login', [
            'email' => 'user@test.dokta',
            'password' => 'mauvais-mot-de-passe',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertGuest('web');
    }

    public function test_un_utilisateur_inactif_ne_peut_pas_se_connecter(): void
    {
        User::factory()->create([
            'email' => 'inactif@test.dokta',
            'password' => Hash::make('secret123'),
            'actif' => false,
        ]);

        $response = $this->depuisFrontend()->postJson('/api/auth/login', [
            'email' => 'inactif@test.dokta',
            'password' => 'secret123',
        ]);

        $response->assertUnprocessable();
        $this->assertGuest('web');
    }

    public function test_une_route_protegee_rejette_une_requete_non_authentifiee(): void
    {
        $this->getJson('/api/patients')->assertUnauthorized();
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_la_deconnexion_invalide_la_session(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret123')]);

        $this->depuisFrontend()->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ])->assertOk();

        $this->assertAuthenticatedAs($user, 'web');

        $this->depuisFrontend()->postJson('/api/auth/logout')->assertOk();

        $this->assertGuest('web');

        // app('auth')->forgetGuards() : entre deux requêtes HTTP simulées
        // dans un même test, le guard Sanctum reste mis en cache côté
        // conteneur applicatif (ce conteneur n'est pas recréé comme il le
        // serait entre deux vraies requêtes HTTP en production) — sans ce
        // reset, la requête suivante verrait encore l'ancien utilisateur
        // authentifié. Pur artefact du harnais de test PHPUnit/Laravel.
        app('auth')->forgetGuards();
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_le_rate_limiting_bloque_les_tentatives_de_connexion_repetees(): void
    {
        User::factory()->create(['email' => 'cible@test.dokta', 'password' => Hash::make('secret123')]);

        for ($i = 0; $i < 5; $i++) {
            $this->depuisFrontend()->postJson('/api/auth/login', [
                'email' => 'cible@test.dokta',
                'password' => 'mauvais-mot-de-passe',
            ])->assertUnprocessable();
        }

        // La 6e tentative sur le même couple IP+identifiant est throttlée.
        $this->depuisFrontend()->postJson('/api/auth/login', [
            'email' => 'cible@test.dokta',
            'password' => 'mauvais-mot-de-passe',
        ])->assertStatus(429);
    }

    // Note sur la protection CSRF : le middleware PreventRequestForgery
    // (Sec-Fetch-Site / jeton X-XSRF-TOKEN), activé par statefulApi() dans
    // bootstrap/app.php, est volontairement désactivé par Laravel dans
    // l'environnement de test (PreventRequestForgery::runningUnitTests()).
    // C'est un comportement du framework, non re-testable ici sans
    // désactiver ce bypass — voir SECURITY.md pour le détail.
}
