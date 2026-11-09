<?php

namespace Database\Factories;

use App\Enums\DestinoSalida;
use App\Models\SalidaAlmacen;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Solo la cabecera: las líneas y el kardex los arma el controlador (o
 * `Kardex` en las pruebas).
 *
 * @extends Factory<SalidaAlmacen>
 */
class SalidaAlmacenFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fecha' => now()->toDateString(),
            'destino' => DestinoSalida::Consumo,
            'vehiculo_id' => null,
            'orden_trabajo_id' => null,
            'entregado_a' => fake()->name(),
        ];
    }
}
