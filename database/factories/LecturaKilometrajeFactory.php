<?php

namespace Database\Factories;

use App\Models\LecturaKilometraje;
use App\Models\Vehiculo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LecturaKilometraje>
 */
class LecturaKilometrajeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vehiculo_id' => Vehiculo::factory(),
            'kilometraje' => fake()->numberBetween(100000, 600000),
            'leido_en' => now(),
            'user_id' => null,
            'observacion' => null,
        ];
    }
}
