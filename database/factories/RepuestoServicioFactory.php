<?php

namespace Database\Factories;

use App\Models\RepuestoServicio;
use App\Models\ServicioMantenimiento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RepuestoServicio>
 */
class RepuestoServicioFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'servicio_mantenimiento_id' => ServicioMantenimiento::factory(),
            'descripcion' => 'Filtro de '.fake()->word(),
            'cantidad' => 1,
            'unidad' => 'und',
            'orden' => 0,
        ];
    }
}
