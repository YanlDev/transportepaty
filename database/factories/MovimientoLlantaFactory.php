<?php

namespace Database\Factories;

use App\Enums\TipoMovimientoLlanta;
use App\Models\Llanta;
use App\Models\MovimientoLlanta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MovimientoLlanta>
 */
class MovimientoLlantaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'llanta_id' => Llanta::factory(),
            'vehiculo_id' => fn (array $atributos): int => Llanta::query()->whereKey($atributos['llanta_id'])->firstOrFail()->vehiculo_id,
            'tipo' => TipoMovimientoLlanta::Inspeccion,
            'fecha' => now()->toDateString(),
            'posicion' => fn (array $atributos): string => Llanta::query()->whereKey($atributos['llanta_id'])->firstOrFail()->posicion,
            'posicion_destino' => null,
            'kilometraje' => null,
            'profundidad_mm' => fake()->randomFloat(1, 4, 16),
            'observacion' => null,
            'user_id' => null,
        ];
    }
}
