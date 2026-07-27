<?php

namespace Database\Factories;

use App\Enums\EstadoVehiculo;
use App\Enums\TipoCaja;
use App\Enums\TipoVehiculo;
use App\Models\Vehiculo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehiculo>
 */
class VehiculoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        /** @var array<string, list<string>> $modelos */
        $modelos = [
            'INTERNATIONAL' => ['LT625 6X4', 'PROSTAR 6X4'],
            'VOLVO' => ['FH 440 6X4', 'FM 440'],
            'SCANIA' => ['R450 A6X4', 'G410 A6X4'],
            'FREIGHTLINER' => ['CASCADIA 6X4'],
        ];

        $marca = fake()->randomElement(array_keys($modelos));
        $pesoNeto = fake()->numberBetween(7000, 9500);
        $pesoBruto = fake()->numberBetween(25000, 30000);

        return [
            'placa' => strtoupper(fake()->unique()->bothify('???-###')),
            'marca' => $marca,
            'modelo' => fake()->randomElement($modelos[$marca]),
            'anio' => fake()->numberBetween(2016, 2026),
            'tipo' => TipoVehiculo::Tracto,
            'estado' => EstadoVehiculo::Activo,
            'caja' => fake()->randomElement(TipoCaja::cases()),
            'vin' => strtoupper(fake()->unique()->bothify('?#?#?#?#?#?#?#?#?')),
            'numero_motor' => strtoupper(fake()->bothify('??#######')),
            'color' => fake()->randomElement(['BLANCO', 'NEGRO', 'GRIS', 'PLATA', 'ROJO', 'AZUL']),
            'ejes' => 3,
            'peso_neto' => $pesoNeto,
            'peso_bruto' => $pesoBruto,
            'carga_util' => $pesoBruto - $pesoNeto,
            'fecha_adquisicion' => fake()->dateTimeBetween('-6 years', 'now'),
            'observaciones' => null,
        ];
    }

    /**
     * Una carreta: unidad remolcada, sin caja ni motor.
     */
    public function carreta(): static
    {
        return $this->state(fn (): array => [
            'tipo' => TipoVehiculo::Carreta,
            'caja' => null,
            'numero_motor' => null,
            'ejes' => 3,
        ]);
    }

    public function enMantenimiento(): static
    {
        return $this->state(fn (): array => ['estado' => EstadoVehiculo::EnMantenimiento]);
    }
}
