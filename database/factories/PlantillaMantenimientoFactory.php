<?php

namespace Database\Factories;

use App\Models\PlantillaMantenimiento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlantillaMantenimiento>
 */
class PlantillaMantenimientoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nombre' => fake()->randomElement(['Cambio de aceite + filtro', 'Filtro de aire', 'Frenos', 'Refrigerante']),
            'tipo_mantenimiento' => fake()->randomElement(['aceite', 'filtro_aire', 'frenos', 'refrigerante']),
            'intervalo_km' => fake()->randomElement([5000, 10000, 40000]),
            'intervalo_meses' => fake()->randomElement([6, 12, 24]),
            'activo' => true,
            'orden' => 0,
        ];
    }
}
