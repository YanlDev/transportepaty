<?php

namespace Database\Factories;

use App\Models\Mantenimiento;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Mantenimiento>
 */
class MantenimientoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'vehiculo_id' => Vehiculo::factory(),
            'registrado_por' => User::factory(),
            'fecha_realizado' => fake()->dateTimeBetween('-1 year'),
            'odometro' => fake()->numberBetween(5000, 180000),
            'proveedor' => fake()->company(),
            'factura_numero' => fake()->bothify('FACT-####'),
            'costo_total' => fake()->randomFloat(2, 100, 2000),
            'observaciones' => fake()->optional()->sentence(),
        ];
    }

    public function conItems(int $cantidad = 2): static
    {
        return $this->has(
            MantenimientoItemFactory::new()->count($cantidad),
            'items',
        );
    }
}
