<?php

namespace Database\Factories;

use App\Models\CategoriaGasto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CategoriaGasto>
 */
class CategoriaGastoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->words(2, true),
            'activa' => true,
        ];
    }
}
