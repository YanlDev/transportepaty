<?php

namespace Database\Factories;

use App\Enums\EstadoOrdenTrabajo;
use App\Enums\TipoOrdenTrabajo;
use App\Models\OrdenTrabajo;
use App\Models\Vehiculo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrdenTrabajo>
 */
class OrdenTrabajoFactory extends Factory
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
            'tipo' => TipoOrdenTrabajo::Correctiva,
            'servicio_mantenimiento_id' => null,
            'estado' => EstadoOrdenTrabajo::Finalizada,
            'fecha' => now()->toDateString(),
            'kilometraje' => null,
            'trabajo' => fake()->sentence(),
            'tecnico' => fake()->name(),
        ];
    }
}
