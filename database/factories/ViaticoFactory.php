<?php

namespace Database\Factories;

use App\Models\Viatico;
use App\Models\Vuelta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Viatico>
 */
class ViaticoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vuelta_id' => Vuelta::factory(),
            'vehiculo_id' => fn (array $atributos): int => Vuelta::query()->whereKey($atributos['vuelta_id'])->firstOrFail()->tracto_id,
            'conductor_id' => null,
            'linea_regla_abastecimiento_id' => null,
            'concepto' => 'Viático',
            'monto_previsto' => null,
            'monto' => fake()->randomElement([143, 180, 700]),
            'fecha' => now()->toDateString(),
            'numero_operacion' => null,
            'observacion' => null,
            'user_id' => null,
        ];
    }

    /**
     * Anulado, con su motivo.
     */
    public function anulado(): static
    {
        return $this->state(fn (): array => [
            'anulado_at' => now(),
            'motivo_anulacion' => 'Depósito duplicado',
        ]);
    }
}
