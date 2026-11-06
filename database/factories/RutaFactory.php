<?php

namespace Database\Factories;

use App\Enums\SentidoTramo;
use App\Models\Ruta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ruta>
 */
class RutaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => 'Ruta '.fake()->unique()->word(),
            'descripcion' => null,
            'sentido' => SentidoTramo::Ida,
            'es_complemento' => false,
            'paradas' => [['zona_origen' => 'mina', 'zona_destino' => 'pisco', 'tipo_carga' => 'concentrado']],
            'pide_destino' => false,
            'dias_viaje' => null,
            'km_estandar' => null,
            'color' => null,
            'nota' => null,
            'activa' => true,
            'orden' => 0,
        ];
    }
}
