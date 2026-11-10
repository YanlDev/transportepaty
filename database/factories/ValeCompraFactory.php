<?php

namespace Database\Factories;

use App\Enums\EstadoValeCompra;
use App\Enums\Moneda;
use App\Enums\TipoValeCompra;
use App\Models\Proveedor;
use App\Models\User;
use App\Models\ValeCompra;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Solo la cabecera, recién solicitada: las líneas las pone la prueba, y la
 * aprobación con su firma la hace el controlador.
 *
 * @extends Factory<ValeCompra>
 */
class ValeCompraFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tipo' => TipoValeCompra::Normal,
            'estado' => EstadoValeCompra::Solicitado,
            'codigo_publico' => ValeCompra::nuevoCodigoPublico(),
            'codigo_verificacion' => ValeCompra::nuevoCodigoVerificacion(),
            'proveedor_id' => Proveedor::factory(),
            'moneda' => Moneda::Soles,
            'total' => 0,
            'motivo' => fake()->sentence(),
            'sustento_precio' => 'Precio de la última compra al mismo proveedor.',
            'solicitado_por' => User::factory(),
        ];
    }

    public function emergencia(): static
    {
        return $this->state(fn (): array => ['tipo' => TipoValeCompra::Emergencia, 'sustento_precio' => null]);
    }
}
