<?php

namespace Database\Factories;

use App\Enums\MedioPago;
use App\Models\PagoCompra;
use App\Models\ValeCompra;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PagoCompra>
 */
class PagoCompraFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vale_compra_id' => ValeCompra::factory(),
            'fecha' => now()->toDateString(),
            'monto' => 100,
            'medio' => MedioPago::Transferencia,
            'numero_operacion' => fake()->numerify('########'),
        ];
    }
}
