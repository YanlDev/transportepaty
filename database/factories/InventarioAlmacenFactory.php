<?php

namespace Database\Factories;

use App\Enums\EstadoInventario;
use App\Models\InventarioAlmacen;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventarioAlmacen>
 */
class InventarioAlmacenFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fecha' => now()->toDateString(),
            'estado' => EstadoInventario::Abierto,
        ];
    }
}
