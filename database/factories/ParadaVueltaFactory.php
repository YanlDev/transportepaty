<?php

namespace Database\Factories;

use App\Enums\SentidoTramo;
use App\Enums\TipoCarga;
use App\Enums\ZonaRuta;
use App\Models\ParadaVuelta;
use App\Models\Vuelta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ParadaVuelta>
 */
class ParadaVueltaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vuelta_id' => Vuelta::factory(),
            'sentido' => SentidoTramo::Regreso,
            'orden' => 1,
            'zona_origen' => ZonaRuta::Mina,
            'zona_destino' => ZonaRuta::Pisco,
            'lugar_origen' => 'Antauta',
            'lugar_destino' => 'Paracas',
            'tipo_carga' => TipoCarga::Concentrado,
            'cliente_id' => null,
            'cliente' => 'MINSUR S.A.',
            'fecha' => now()->subDays(3)->toDateString(),
        ];
    }
}
