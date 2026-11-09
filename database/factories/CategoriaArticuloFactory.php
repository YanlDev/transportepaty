<?php

namespace Database\Factories;

use App\Models\CategoriaArticulo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CategoriaArticulo>
 */
class CategoriaArticuloFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->word().' '.fake()->unique()->numberBetween(1, 99999),
        ];
    }
}
