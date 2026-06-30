<?php

namespace Database\Factories;

use App\Models\CargaCombustible;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CargaCombustible>
 */
class CargaCombustibleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * A processed load by default (has odometer + gallons).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $galones = fake()->randomFloat(3, 5, 20);
        $precio = fake()->randomFloat(3, 14, 18);

        return [
            'vehiculo_id' => Vehiculo::factory(),
            'registrada_por' => User::factory(),
            'fecha_carga' => fake()->dateTimeBetween('-3 months', 'now'),
            'odometro' => fake()->numberBetween(10000, 180000),
            'galones' => $galones,
            'precio_por_galon' => $precio,
            'costo_total' => round($galones * $precio, 2),
            'observaciones' => null,
            'procesada_por' => User::factory(),
            'procesada_en' => now(),
        ];
    }

    /**
     * A load that the driver only photographed; the admin has not filled it yet.
     */
    public function pendiente(): static
    {
        return $this->state(fn (): array => [
            'odometro' => null,
            'galones' => null,
            'costo_total' => null,
            'precio_por_galon' => null,
            'procesada_por' => null,
            'procesada_en' => null,
        ]);
    }
}
