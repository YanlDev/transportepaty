<?php

namespace Database\Factories;

use App\Enums\Moneda;
use App\Models\CuentaBancaria;
use App\Models\Factura;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Factura>
 */
class FacturaFactory extends Factory
{
    protected $model = Factura::class;

    /**
     * Por defecto sale emitida y sin cobrar, que es el estado en que una
     * factura pasa la mayor parte de su vida y el que interesa probar.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'numero' => 'F001-'.$this->faker->unique()->numerify('#####'),
            'fecha_emision' => $this->faker->dateTimeBetween('-3 months', 'now'),
            'monto' => $this->faker->randomFloat(2, 800, 12000),
            'moneda' => Moneda::Soles,
            'fecha_pago' => null,
            'cuenta_bancaria_id' => null,
            'observacion' => null,
        ];
    }

    /**
     * Cobrada del todo: el neto en una cuenta de la empresa y la detracción
     * en el Banco de la Nación, el mismo día.
     */
    public function pagada(): static
    {
        return $this->state(function (): array {
            $fecha = $this->faker->dateTimeBetween('-2 months', 'now');

            return [
                'fecha_pago' => $fecha,
                'cuenta_bancaria_id' => CuentaBancaria::factory(),
                'fecha_detraccion' => $fecha,
            ];
        });
    }

    /** Cobrado el neto, pero la detracción todavía no se depositó. */
    public function faltaDetraccion(): static
    {
        return $this->state(fn (): array => [
            'fecha_pago' => $this->faker->dateTimeBetween('-2 months', 'now'),
            'cuenta_bancaria_id' => CuentaBancaria::factory(),
            'fecha_detraccion' => null,
        ]);
    }
}
