<?php

namespace Database\Factories;

use App\Models\Conductor;
use App\Models\Sucursal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conductor>
 */
class ConductorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sucursal_id' => Sucursal::factory(),
            'user_id' => null,
            'nombres' => fake()->firstName(),
            'apellidos' => fake()->lastName().' '.fake()->lastName(),
            'documento' => fake()->unique()->numerify('########'),
            'licencia' => fake()->bothify('Q########'),
            'categoria_licencia' => fake()->randomElement(['A-I', 'A-IIa', 'A-IIb', 'A-IIIa']),
            'licencia_vence' => fake()->dateTimeBetween('+1 month', '+4 years'),
            'telefono' => fake()->numerify('+51 9########'),
            'email' => fake()->safeEmail(),
            'activo' => true,
        ];
    }

    public function inactivo(): static
    {
        return $this->state(fn (): array => ['activo' => false]);
    }
}
