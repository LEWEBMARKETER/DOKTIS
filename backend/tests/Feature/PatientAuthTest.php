<?php

namespace Tests\Feature;

use App\Enums\RoleUtilisateur;
use App\Models\Cabinet;
use App\Models\PatientAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PatientAuthTest extends TestCase
{
    use RefreshDatabase;

    private function depuisFrontend(): self
    {
        return $this->withHeader('Referer', 'http://localhost:3000/patient/login');
    }

    public function test_un_patient_peut_creer_un_compte(): void
    {
        $response = $this->depuisFrontend()->postJson('/api/patient/auth/register', [
            'nom' => 'Kamdem',
            'prenom' => 'Sonia',
            'email' => 'sonia@patient.dokta',
            'telephone' => '690112233',
            'password' => 'password123',
        ]);

        $response->assertCreated()->assertJsonStructure(['account'])->assertJsonMissingPath('token');
        $this->assertDatabaseHas('patient_accounts', ['email' => 'sonia@patient.dokta']);
        $this->assertAuthenticatedAs(PatientAccount::where('email', 'sonia@patient.dokta')->first(), 'patient');
    }

    public function test_un_patient_peut_se_connecter_avec_email_ou_telephone(): void
    {
        PatientAccount::factory()->create([
            'email' => 'a@patient.dokta',
            'telephone' => '690000001',
            'password' => Hash::make('password'),
        ]);

        $this->depuisFrontend()
            ->postJson('/api/patient/auth/login', ['identifiant' => 'a@patient.dokta', 'password' => 'password'])
            ->assertOk()
            ->assertJsonMissingPath('token');

        $this->depuisFrontend()
            ->postJson('/api/patient/auth/login', ['identifiant' => '690000001', 'password' => 'password'])
            ->assertOk();
    }

    public function test_la_connexion_patient_echoue_avec_un_mauvais_mot_de_passe(): void
    {
        PatientAccount::factory()->create(['email' => 'a@patient.dokta', 'password' => Hash::make('password')]);

        $this->depuisFrontend()
            ->postJson('/api/patient/auth/login', ['identifiant' => 'a@patient.dokta', 'password' => 'mauvais'])
            ->assertUnprocessable();

        $this->assertGuest('patient');
    }

    public function test_une_route_patient_protegee_rejette_une_requete_non_authentifiee(): void
    {
        $this->getJson('/api/patient/rendez-vous')->assertUnauthorized();
        $this->getJson('/api/patient/auth/me')->assertUnauthorized();
    }

    public function test_la_deconnexion_patient_invalide_la_session(): void
    {
        $account = PatientAccount::factory()->create(['password' => Hash::make('password')]);

        $this->depuisFrontend()->postJson('/api/patient/auth/login', [
            'identifiant' => $account->email,
            'password' => 'password',
        ])->assertOk();

        $this->assertAuthenticatedAs($account, 'patient');

        $this->depuisFrontend()->postJson('/api/patient/auth/logout')->assertOk();

        $this->assertGuest('patient');

        // Voir AuthTest::test_la_deconnexion_invalide_la_session : reset
        // nécessaire uniquement à cause du cache de guard entre requêtes
        // HTTP simulées dans un même test (pas un comportement de prod).
        app('auth')->forgetGuards();
        $this->getJson('/api/patient/auth/me')->assertUnauthorized();
    }

    public function test_un_compte_patient_ne_peut_pas_acceder_aux_routes_du_cabinet(): void
    {
        $account = PatientAccount::factory()->create(['password' => Hash::make('password')]);

        $this->depuisFrontend()->postJson('/api/patient/auth/login', [
            'identifiant' => $account->email,
            'password' => 'password',
        ])->assertOk();

        $this->getJson('/api/patients')->assertForbidden();
        $this->getJson('/api/auth/me')->assertForbidden();
    }

    public function test_un_compte_cabinet_ne_peut_pas_acceder_aux_routes_patient(): void
    {
        $cabinet = Cabinet::factory()->create();
        $user = User::factory()->create(['cabinet_id' => $cabinet->id, 'role' => RoleUtilisateur::Administrateur, 'password' => Hash::make('secret123')]);

        $this->depuisFrontend()->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ])->assertOk();

        $this->getJson('/api/patient/auth/me')->assertForbidden();
        $this->getJson('/api/patient/rendez-vous')->assertForbidden();
    }

    /**
     * Les deux applications partagent le même cookie de session (deux gardes
     * distincts, "web" et "patient") : se connecter à l'une ne doit ni
     * connecter l'autre, ni l'invalider si elle l'était déjà.
     */
    public function test_les_sessions_office_et_patient_sont_cloisonnees_dans_le_meme_navigateur(): void
    {
        $cabinet = Cabinet::factory()->create();
        $user = User::factory()->create(['cabinet_id' => $cabinet->id, 'role' => RoleUtilisateur::Administrateur, 'password' => Hash::make('secret123')]);
        $account = PatientAccount::factory()->create(['password' => Hash::make('password')]);

        $this->depuisFrontend()->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ])->assertOk();

        $this->depuisFrontend()->postJson('/api/patient/auth/login', [
            'identifiant' => $account->email,
            'password' => 'password',
        ])->assertOk();

        // Les deux gardes sont authentifiés simultanément, chacun avec le
        // bon compte — aucun écrasement croisé.
        $this->assertAuthenticatedAs($user, 'web');
        $this->assertAuthenticatedAs($account, 'patient');

        // La déconnexion du cabinet ne déconnecte pas le patient.
        $this->depuisFrontend()->postJson('/api/auth/logout')->assertOk();
        $this->assertGuest('web');
        $this->assertAuthenticatedAs($account, 'patient');
    }
}
