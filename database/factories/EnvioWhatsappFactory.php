<?php

namespace Database\Factories;

use App\Enums\EstadoEnvio;
use App\Models\EnvioWhatsapp;
use App\Models\Programacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EnvioWhatsapp>
 */
class EnvioWhatsappFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'programacion_id' => Programacion::factory(),
            'tipo' => EnvioWhatsapp::TIPO_CONDUCTOR,
            'destino' => 'Conductor',
            'numero' => '519'.fake()->numerify('########'),
            'estado' => EstadoEnvio::Pendiente,
        ];
    }

    public function enviado(string $mensajeId = 'MSG1'): static
    {
        return $this->state(['estado' => EstadoEnvio::Enviado, 'mensaje_id' => $mensajeId, 'enviado_at' => now()]);
    }
}
