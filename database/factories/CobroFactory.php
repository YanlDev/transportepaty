<?php

namespace Database\Factories;

use App\Enums\Moneda;
use App\Models\Cobro;
use App\Models\CuentaBancaria;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cobro>
 */
class CobroFactory extends Factory
{
    /**
     * Un depósito en una cuenta de la empresa, todavía sin repartir.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fecha' => $this->faker->dateTimeBetween('-2 months', 'now'),
            'cuenta_bancaria_id' => CuentaBancaria::factory(),
            'numero_operacion' => $this->faker->numerify('########'),
            'monto' => $this->faker->randomFloat(2, 500, 9000),
            'moneda' => Moneda::Soles,
        ];
    }
}
