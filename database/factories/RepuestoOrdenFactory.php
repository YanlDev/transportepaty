<?php

namespace Database\Factories;

use App\Models\OrdenTrabajo;
use App\Models\RepuestoOrden;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RepuestoOrden>
 */
class RepuestoOrdenFactory extends Factory
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
            'articulo_id' => null,
            'descripcion' => fake()->words(3, true),
            'cantidad' => 1,
            'unidad' => 'und',
            'adicional' => false,
        ];
    }
}
