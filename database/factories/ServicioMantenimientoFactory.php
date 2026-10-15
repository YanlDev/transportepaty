<?php

namespace Database\Factories;

use App\Models\PlanMantenimiento;
use App\Models\ServicioMantenimiento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServicioMantenimiento>
 */
class ServicioMantenimientoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plan_mantenimiento_id' => PlanMantenimiento::factory(),
            'codigo' => strtoupper(fake()->unique()->lexify('??')),
            'nombre' => 'Servicio '.fake()->word(),
            'nivel' => 1,
            'intervalo_km' => 20000,
            'intervalo_meses' => null,
        ];
    }
}
