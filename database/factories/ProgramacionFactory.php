<?php

namespace Database\Factories;

use App\Enums\EstadoProgramacion;
use App\Models\Programacion;
use App\Models\Vehiculo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Programacion>
 */
class ProgramacionFactory extends Factory
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
            'fecha' => fake()->dateTimeBetween('-1 week', 'now')->format('Y-m-d'),
            'estado' => fake()->randomElement(EstadoProgramacion::cases()),
            'observaciones' => null,
        ];
    }
}
