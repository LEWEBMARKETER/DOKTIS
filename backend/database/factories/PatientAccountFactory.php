<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PatientAccount>
 */
class PatientAccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nom' => fake('fr_FR')->lastName(),
            'prenom' => fake('fr_FR')->firstName(),
            'email' => fake()->unique()->safeEmail(),
            'telephone' => '6'.fake()->unique()->numerify('########'),
            'password' => Hash::make('password'),
            'actif' => true,
        ];
    }
}
