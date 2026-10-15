<?php

namespace Database\Factories;

use App\Models\PlanMantenimiento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanMantenimiento>
 */
class PlanMantenimientoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'marca' => strtoupper(fake()->unique()->lexify('MARCA????')),
            'nombre' => 'Plan '.fake()->word(),
        ];
    }
}
