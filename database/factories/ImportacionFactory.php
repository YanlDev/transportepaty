<?php

namespace Database\Factories;

use App\Models\Importacion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Importacion>
 */
class ImportacionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fecha' => fake()->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
            'archivo_original' => 'Disponibilidad de unidades.xlsx',
            'subido_por' => User::factory(),
        ];
    }

    public function confirmada(): static
    {
        return $this->state(fn (): array => ['confirmado_en' => now()]);
    }
}
