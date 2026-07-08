<?php

namespace Database\Factories;

use App\Enums\ResultadoActivacion;
use App\Models\Activacion;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activacion>
 */
class ActivacionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vehiculo_id' => Vehiculo::factory(),
            'conductor_id' => null,
            'registrada_por' => User::factory(),
            'fecha' => fake()->dateTimeBetween('-2 months', 'now'),
            'kilometraje' => fake()->numberBetween(10000, 180000),
            'resultado' => ResultadoActivacion::SinNovedad,
            'observaciones' => null,
        ];
    }

    /**
     * Una activación en la que el responsable detectó una anomalía.
     */
    public function conAnomalia(): static
    {
        return $this->state(fn (): array => [
            'resultado' => ResultadoActivacion::Anomalia,
            'observaciones' => fake()->sentence(),
        ]);
    }
}
