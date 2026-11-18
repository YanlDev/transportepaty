<?php

namespace Database\Factories;

use App\Models\OrdenTrabajo;
use App\Models\TareaOrden;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TareaOrden>
 */
class TareaOrdenFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'orden_trabajo_id' => OrdenTrabajo::factory(),
            'descripcion' => fake()->sentence(4),
            'posicion' => 0,
        ];
    }
}
