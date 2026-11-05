<?php

namespace Database\Factories;

use App\Enums\SentidoTramo;
use App\Enums\TipoLineaAbastecimiento;
use App\Models\DotacionTramo;
use App\Models\Vuelta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DotacionTramo>
 */
class DotacionTramoFactory extends Factory
{
    /**
     * Una tarjeta de 220 galones (150 en Juliaca y 70 en Arequipa) y un
     * viático de S/ 143.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vuelta_id' => Vuelta::factory(),
            'sentido' => SentidoTramo::Ida,
            'regla_abastecimiento_id' => null,
            'nombre' => 'Juliaca – Mina – Lima',
            'color' => null,
            'lineas' => [
                ['linea_id' => null, 'tipo' => TipoLineaAbastecimiento::Combustible->value, 'concepto' => 'Combustible', 'punto_id' => null, 'punto' => 'Juliaca', 'galones' => 150.0, 'monto' => null],
                ['linea_id' => null, 'tipo' => TipoLineaAbastecimiento::Combustible->value, 'concepto' => 'Combustible', 'punto_id' => null, 'punto' => 'Arequipa', 'galones' => 70.0, 'monto' => null],
                ['linea_id' => null, 'tipo' => TipoLineaAbastecimiento::Viatico->value, 'concepto' => 'Viático', 'punto_id' => null, 'punto' => null, 'galones' => null, 'monto' => 143.0],
            ],
            'user_id' => null,
        ];
    }

    public function regreso(): static
    {
        return $this->state(['sentido' => SentidoTramo::Regreso]);
    }
}
