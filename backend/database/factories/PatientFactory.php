<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Patient>
 */
class PatientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'numero_dossier' => 'TEST-'.fake()->unique()->numerify('####'),
            'nom' => fake('fr_FR')->lastName(),
            'prenom' => fake('fr_FR')->firstName(),
            'date_naissance' => fake()->date(),
            'sexe' => fake()->randomElement(['M', 'F']),
            'telephone' => fake()->phoneNumber(),
        ];
    }
}
