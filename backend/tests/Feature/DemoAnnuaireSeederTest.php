<?php

namespace Tests\Feature;

use App\Models\Cabinet;
use App\Models\Creneau;
use App\Models\User;
use Database\Seeders\DemoAnnuaireSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoAnnuaireSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_demo_est_idempotente_et_sans_numero_invente(): void
    {
        config(['dokta.demo' => ['actif' => true, 'whatsapp' => null, 'password' => null]]);

        $this->seed(DemoAnnuaireSeeder::class);
        $this->seed(DemoAnnuaireSeeder::class);

        $demo = Cabinet::where('plan', DemoAnnuaireSeeder::PLAN)->get();
        $this->assertCount(8, $demo);
        $this->assertTrue($demo->every(fn (Cabinet $c) => $c->whatsapp === null));
        $this->assertTrue($demo->every(fn (Cabinet $c) => str_contains($c->description, 'Fiche de démonstration')));

        $etoile = $demo->firstWhere('slug', 'demo-cabinet-dentaire-etoile-libreville');
        $this->assertGreaterThan(0, Creneau::withoutGlobalScopes()->where('cabinet_id', $etoile->id)->where('debut', '>', now())->count());

        $this->postJson('/api/auth/login', ['email' => 'praticien.etoile@demo.doktis', 'password' => ''])->assertUnprocessable();
        $this->assertSame(2, User::where('cabinet_id', $etoile->id)->count());

        $this->getJson('/api/directory/localites')->assertOk()->assertJsonFragment(['ville' => 'Owendo']);
    }

    public function test_le_numero_whatsapp_et_le_mot_de_passe_de_demo_sont_appliques(): void
    {
        config(['dokta.demo' => ['actif' => true, 'whatsapp' => '077 12 34 56', 'password' => 'demo-mot-de-passe']]);

        $this->seed(DemoAnnuaireSeeder::class);

        $this->assertSame(8, Cabinet::where('whatsapp', '24177123456')->count());
        $this->postJson('/api/auth/login', ['email' => 'praticien.etoile@demo.doktis', 'password' => 'demo-mot-de-passe'])
            ->assertOk()
            ->assertJsonPath('user.role', 'administrateur');
    }
}
