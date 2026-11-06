<?php

namespace Database\Factories;

use App\Enums\SentidoTramo;
use App\Enums\TipoLineaAbastecimiento;
use App\Models\RutaVuelta;
use App\Models\Vuelta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RutaVuelta>
 */
class RutaVueltaFactory extends Factory
{
    /**
     * Una ruta de 220 galones (150 en Juliaca y 70 en Arequipa) y un viático
     * de S/ 143.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vuelta_id' => Vuelta::factory(),
            'sentido' => SentidoTramo::Ida,
            'ruta_id' => null,
            'variante_ruta_id' => null,
            'es_complemento' => false,
            'nombre' => 'Concentrado a Pisco',
            'variante' => null,
            'color' => null,
            'lineas' => [
                ['linea_id' => null, 'tipo' => TipoLineaAbastecimiento::Combustible->value, 'concepto' => 'Combustible', 'punto_id' => null, 'punto' => 'Juliaca', 'galones' => 150.0, 'monto' => null],
                ['linea_id' => null, 'tipo' => TipoLineaAbastecimiento::Combustible->value, 'concepto' => 'Combustible', 'punto_id' => null, 'punto' => 'Arequipa', 'galones' => 70.0, 'monto' => null],
                ['linea_id' => null, 'tipo' => TipoLineaAbastecimiento::Viatico->value, 'concepto' => 'Viático', 'punto_id' => null, 'punto' => null, 'galones' => null, 'monto' => 143.0],
            ],
            'variante_pendiente' => false,
            'dias_viaje' => null,
            'km_estandar' => null,
            'orden' => 1,
            'user_id' => null,
        ];
    }

    public function regreso(): static
    {
        return $this->state(['sentido' => SentidoTramo::Regreso]);
    }
}
