<?php

namespace Database\Factories;

use App\Models\PuntoAbastecimiento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PuntoAbastecimiento>
 */
class PuntoAbastecimientoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->city(),
            'zona' => null,
            'detalle' => null,
            'activo' => true,
            'orden' => 0,
        ];
    }
}
