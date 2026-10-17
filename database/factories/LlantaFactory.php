<?php

namespace Database\Factories;

use App\Enums\CondicionLlanta;
use App\Models\Llanta;
use App\Models\Vehiculo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Llanta>
 */
class LlantaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vehiculo_id' => Vehiculo::factory(),
            'posicion' => '1',
            'marca' => fake()->randomElement(['Michelin', 'Bridgestone', 'Goodyear', 'Continental']),
            'medida' => '295/80R22.5',
            'codigo' => null,
            'condicion' => CondicionLlanta::Nueva,
            'instalada_el' => now()->subMonths(3)->toDateString(),
            'km_instalacion' => null,
            'retirada_el' => null,
            'km_retiro' => null,
            'motivo_retiro' => null,
        ];
    }
}
