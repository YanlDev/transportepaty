<?php

namespace Database\Factories;

use App\Enums\TipoVehiculo;
use App\Models\Abastecimiento;
use App\Models\Vehiculo;
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
            'punto_abastecimiento_id' => null,
            'conductor_id' => null,
            'fecha' => now()->toDateString(),
            'galones' => 100,
            'tanque_lleno' => false,
            'precio_galon' => null,
            'importe' => null,
            'observacion' => null,
            'user_id' => null,
        ];
    }
}
