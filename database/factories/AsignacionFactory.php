<?php

namespace Database\Factories;

use App\Models\Asignacion;
use App\Models\Conductor;
use App\Models\Vehiculo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asignacion>
 */
class AsignacionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conductor_id' => Conductor::factory(),
            'tracto_id' => Vehiculo::factory(),
            'carreta_id' => Vehiculo::factory()->carreta(),
            'desde' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'hasta' => null,
            'observaciones' => null,
        ];
    }

    /**
     * Una asignación ya cerrada, es decir, parte del historial.
     */
    public function finalizada(): static
    {
        return $this->state(fn (array $atributos): array => [
            'hasta' => fake()->dateTimeBetween($atributos['desde'], 'now')->format('Y-m-d'),
        ]);
    }

    /**
     * Un tracto asignado sin carreta, como cuando la unidad queda desenganchada.
     */
    public function sinCarreta(): static
    {
        return $this->state(fn (): array => ['carreta_id' => null]);
    }
}
