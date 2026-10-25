<?php

namespace Database\Factories;

use App\Enums\EstadoVuelta;
use App\Enums\OrigenVuelta;
use App\Enums\TipoVehiculo;
use App\Enums\TipoVuelta;
use App\Models\Vehiculo;
use App\Models\Vuelta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vuelta>
 */
class VueltaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tracto_id' => Vehiculo::factory()->state(['tipo' => TipoVehiculo::Tracto]),
            'carreta_id' => null,
            'conductor_id' => null,
            'tipo' => TipoVuelta::Mina,
            'estado' => EstadoVuelta::EnCurso,
            'origen' => OrigenVuelta::Historial,
            'inicio' => now()->subDays(5)->toDateString(),
            'fin' => null,
        ];
    }

    /**
     * Cargada a mano por Programación: la reconstrucción no la toca.
     */
    public function programada(): static
    {
        return $this->state(['origen' => OrigenVuelta::Programacion, 'estado' => EstadoVuelta::Programada]);
    }
}
