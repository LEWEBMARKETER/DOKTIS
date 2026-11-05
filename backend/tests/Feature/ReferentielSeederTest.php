<?php

namespace Tests\Feature;

use App\Models\Mutuelle;
use App\Models\Specialite;
use App\Models\User;
use Database\Seeders\ReferentielSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReferentielSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_seeder_de_production_est_idempotent_et_cree_le_super_admin(): void
    {
        config(['dokta.super_admin' => ['email' => 'equipe@doktis.test', 'password' => 'secret-tres-long', 'nom' => 'Équipe DOKTIS']]);

        $this->seed(ReferentielSeeder::class);
        $this->seed(ReferentielSeeder::class);

        $this->assertSame(count(ReferentielSeeder::SPECIALITES), Specialite::count());
        $this->assertSame(count(ReferentielSeeder::ASSUREURS), Mutuelle::count());

        $admins = User::where('email', 'equipe@doktis.test')->get();
        $this->assertCount(1, $admins);
        $this->assertSame('super_admin', $admins->first()->role->value);
        $this->assertTrue(Hash::check('secret-tres-long', $admins->first()->password));
    }

    public function test_sans_variables_aucun_super_admin_nest_cree(): void
    {
        config(['dokta.super_admin' => ['email' => null, 'password' => null, 'nom' => 'Équipe DOKTIS']]);

        $this->seed(ReferentielSeeder::class);

        $this->assertSame(0, User::count());
        $this->getJson('/')->assertOk()->assertJsonPath('service', 'DOKTIS API');
    }
}
