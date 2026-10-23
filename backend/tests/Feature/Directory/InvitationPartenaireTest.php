<?php

namespace Tests\Feature\Directory;

use App\Enums\RoleUtilisateur;
use App\Models\Cabinet;
use App\Models\InvitationPartenaire;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Couvre la mission Partie D : un partenaire n'obtient jamais de compte
 * par inscription libre, uniquement via un lien d'invitation à usage
 * unique envoyé par un administrateur DOKTIS après création de la fiche.
 */
class InvitationPartenaireTest extends TestCase
{
    use RefreshDatabase;

    private function depuisFrontend(): self
    {
        return $this->withHeader('Referer', 'http://localhost:3000/partenaire/activer');
    }

    private function creerFicheEtInviter(string $email = 'partenaire@clinique.ga'): array
    {
        $admin = User::factory()->create(['role' => RoleUtilisateur::SuperAdmin]);
        $cabinet = Cabinet::factory()->create();

        $reponse = $this->actingAs($admin)->postJson("/api/admin/fiches/{$cabinet->id}/invitations", ['email' => $email]);
        $reponse->assertCreated();

        $lien = $reponse->json('lien_activation');
        parse_str(parse_url($lien, PHP_URL_QUERY), $params);

        return [$cabinet, $params['invitation'], $params['jeton']];
    }

    public function test_un_partenaire_active_son_compte_via_le_lien_dinvitation_et_se_connecte(): void
    {
        [$cabinet, $invitationId, $jeton] = $this->creerFicheEtInviter();

        $this->depuisFrontend()->postJson('/api/partenaire/auth/activer', [
            'invitation' => $invitationId,
            'jeton' => $jeton,
            'password' => 'motdepasse123',
        ])->assertCreated()->assertJsonPath('user.role', 'partenaire');

        $compte = User::where('email', 'partenaire@clinique.ga')->first();
        $this->assertNotNull($compte);
        $this->assertEquals($cabinet->id, $compte->cabinet_id);
        $this->assertAuthenticatedAs($compte, 'web');

        $this->assertNotNull(InvitationPartenaire::find($invitationId)->utilise_at);
    }

    public function test_lactivation_echoue_avec_un_jeton_invalide(): void
    {
        [, $invitationId] = $this->creerFicheEtInviter();

        $this->depuisFrontend()->postJson('/api/partenaire/auth/activer', [
            'invitation' => $invitationId,
            'jeton' => 'un-jeton-incorrect',
            'password' => 'motdepasse123',
        ])->assertUnprocessable();
    }

    public function test_un_lien_dactivation_deja_utilise_est_refuse(): void
    {
        [, $invitationId, $jeton] = $this->creerFicheEtInviter();

        // Simule un lien déjà consommé sans enchaîner une 2e requête HTTP
        // après une 1re connexion dans le même test : cet enchaînement
        // précis réutilise le garde "web" mis en cache par le client de
        // test PHPUnit entre deux requêtes simulées (artefact du harnais,
        // déjà rencontré et documenté lors de l'audit de sécurité —
        // SECURITY.md) et ne reflète pas un comportement de production.
        InvitationPartenaire::find($invitationId)->update(['utilise_at' => now()]);

        $this->depuisFrontend()->postJson('/api/partenaire/auth/activer', [
            'invitation' => $invitationId,
            'jeton' => $jeton,
            'password' => 'autremotdepasse',
        ])->assertUnprocessable();
    }

    public function test_un_partenaire_actif_na_aucun_droit_office(): void
    {
        [$cabinet] = $this->creerFicheEtInviter();
        $partenaire = User::factory()->create(['cabinet_id' => $cabinet->id, 'role' => RoleUtilisateur::Partenaire]);

        $this->actingAs($partenaire)->getJson('/api/patients')->assertForbidden();
        $this->actingAs($partenaire)->getJson('/api/dashboard/stats')->assertForbidden();
        $this->actingAs($partenaire)->getJson('/api/users')->assertForbidden();
    }
}
