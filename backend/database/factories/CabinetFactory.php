<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cabinet>
 */
class CabinetFactory extends Factory
{
    public function definition(): array
    {
        $nom = fake('fr_FR')->company();

        return [
            'nom' => $nom,
            'slug' => Str::slug($nom).'-'.Str::lower(Str::random(6)),
            'type' => fake()->randomElement(['dentaire', 'medical', 'mixte']),
            'telephone' => fake()->phoneNumber(),
            'email' => fake()->companyEmail(),
            'plan' => 'essai',
            'actif' => true,
        ];
    }
}
