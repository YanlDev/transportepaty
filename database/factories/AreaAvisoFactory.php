<?php

namespace Database\Factories;

use App\Models\AreaAviso;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AreaAviso>
 */
class AreaAvisoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->randomElement(['Abastecimiento', 'Centro de Control', 'Operaciones']),
            'numero' => '9'.fake()->numerify('########'),
            've_flete' => false,
            'activa' => true,
            'orden' => 0,
        ];
    }

    /** Un área que recibe también el flete acordado, como facturación. */
    public function veFlete(): static
    {
        return $this->state(['nombre' => 'Facturación', 've_flete' => true]);
    }

    public function inactiva(): static
    {
        return $this->state(['activa' => false]);
    }
}
