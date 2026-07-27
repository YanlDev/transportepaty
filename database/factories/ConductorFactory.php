<?php

namespace Database\Factories;

use App\Models\Conductor;
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
            'user_id' => null,
            'nombres' => fake()->firstName(),
            'apellidos' => fake()->lastName().' '.fake()->lastName(),
            'documento' => fake()->unique()->numerify('########'),
            'licencia' => fake()->bothify('Q########'),
            'categoria_licencia' => fake()->randomElement(['A-IIIa', 'A-IIIb', 'A-IIIc']),
            'licencia_vence' => fake()->dateTimeBetween('+1 month', '+4 years'),
            'telefono' => fake()->numerify('9########'),
            'email' => fake()->safeEmail(),
            'fecha_nacimiento' => fake()->dateTimeBetween('-60 years', '-25 years'),
            'procedencia' => fake()->randomElement(['Puno', 'Cusco', 'Arequipa', 'Apurimac']),
            'activo' => true,
        ];
    }

    public function inactivo(): static
    {
        return $this->state(fn (): array => ['activo' => false]);
    }
}
