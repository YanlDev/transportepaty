<?php

namespace Database\Factories;

use App\Enums\Moneda;
use App\Enums\TipoComprobante;
use App\Enums\TipoIngreso;
use App\Models\IngresoAlmacen;
use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Solo la cabecera: las líneas y el kardex los arma el controlador (o
 * `Kardex` en las pruebas).
 *
 * @extends Factory<IngresoAlmacen>
 */
class IngresoAlmacenFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tipo' => TipoIngreso::Compra,
            'fecha' => now()->toDateString(),
            'proveedor_id' => Proveedor::factory(),
            'tipo_comprobante' => TipoComprobante::Factura,
            'serie_numero' => 'F001-'.fake()->unique()->numerify('######'),
            'moneda' => Moneda::Soles,
        ];
    }
}
