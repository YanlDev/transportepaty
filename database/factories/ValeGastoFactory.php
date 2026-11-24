<?php

namespace Database\Factories;

use App\Enums\AreaGasto;
use App\Enums\EstadoValeCompra;
use App\Models\CategoriaGasto;
use App\Models\User;
use App\Models\ValeGasto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Un vale recién pedido, general del área: la aprobación la hace el
 * controlador.
 *
 * @extends Factory<ValeGasto>
 */
class ValeGastoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'estado' => EstadoValeCompra::Solicitado,
            'area' => AreaGasto::Operaciones,
            'categoria_gasto_id' => CategoriaGasto::factory(),
            'concepto' => fake()->sentence(3),
            'motivo' => fake()->sentence(),
            'monto' => 150,
            'beneficiario' => fake()->name(),
            'solicitado_por' => User::factory(),
        ];
    }

    public function aprobado(): static
    {
        return $this->state(fn (): array => [
            'estado' => EstadoValeCompra::Aprobado,
            'aprobado_por' => User::factory(),
            'aprobado_at' => now(),
        ]);
    }
}
