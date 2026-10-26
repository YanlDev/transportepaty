<?php

namespace Database\Factories;

use App\Models\ReglaAbastecimiento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReglaAbastecimiento>
 */
class ReglaAbastecimientoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->words(3, true),
            'color' => 'gris',
            'activa' => true,
            'orden' => 0,
        ];
    }
}
