<?php

namespace Database\Factories;

use App\Enums\TipoNovedad;
use App\Models\Novedad;
use App\Models\Vehiculo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Novedad>
 */
class NovedadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tracto_id' => Vehiculo::factory(),
            'tipo' => TipoNovedad::NoHabido,
            'desde' => now()->toDateString(),
            'hasta' => null,
            'motivo' => null,
        ];
    }

    public function de(TipoNovedad $tipo): static
    {
        return $this->state(fn (): array => ['tipo' => $tipo]);
    }

    /**
     * Una novedad ya levantada, que por lo tanto no debería pesar hoy.
     */
    public function levantada(string $hasta): static
    {
        return $this->state(fn (): array => ['hasta' => $hasta]);
    }
}
