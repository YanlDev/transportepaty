<?php

namespace Database\Factories;

use App\Enums\SentidoTramo;
use App\Enums\TipoVehiculo;
use App\Models\Abastecimiento;
use App\Models\Vehiculo;
use App\Models\Vuelta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Abastecimiento>
 */
class AbastecimientoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vehiculo_id' => Vehiculo::factory()->state(['tipo' => TipoVehiculo::Tracto]),
            'vuelta_id' => null,
            'sentido' => SentidoTramo::Ida,
            'punto_abastecimiento_id' => null,
            'conductor_id' => null,
            'fecha' => now()->toDateString(),
            'galones' => 100,
            'precio_galon' => null,
            'importe' => null,
            'observacion' => null,
            'user_id' => null,
        ];
    }

    /**
     * Para el ticket: la unidad es la del ticket.
     */
    public function paraVuelta(Vuelta $vuelta, SentidoTramo $sentido = SentidoTramo::Ida): static
    {
        return $this->state(['vehiculo_id' => $vuelta->tracto_id, 'vuelta_id' => $vuelta->id, 'sentido' => $sentido]);
    }

    public function anulado(): static
    {
        return $this->state(['anulado_at' => now(), 'motivo_anulacion' => 'Se registró dos veces.']);
    }
}
