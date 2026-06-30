<?php

namespace Database\Factories;

use App\Enums\EstadoVehiculo;
use App\Enums\TipoCombustible;
use App\Enums\TipoVehiculo;
use App\Models\Conductor;
use App\Models\Sucursal;
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
            'Toyota' => ['Hilux', 'Land Cruiser Prado', 'Fortuner', 'Yaris'],
            'Hyundai' => ['Tucson', 'Santa Fe', 'Accent'],
            'Nissan' => ['Frontier', 'Navara', 'Sentra'],
            'Mitsubishi' => ['L200', 'Montero Sport'],
        ];

        $marca = fake()->randomElement(array_keys($modelos));
        $modelo = fake()->randomElement($modelos[$marca]);

        return [
            'sucursal_id' => Sucursal::factory(),
            'conductor_id' => null,
            'placa' => strtoupper(fake()->unique()->bothify('???-###')),
            'marca' => $marca,
            'modelo' => $modelo,
            'anio' => fake()->numberBetween(2016, 2026),
            'color' => fake()->randomElement(['Blanco', 'Negro', 'Gris', 'Plata', 'Rojo', 'Azul']),
            'numero_serie' => strtoupper(fake()->unique()->bothify('?#?#?#?#?#?#?#?#?')),
            'numero_motor' => strtoupper(fake()->bothify('??#######')),
            'tipo' => fake()->randomElement(TipoVehiculo::cases()),
            'combustible' => fake()->randomElement(TipoCombustible::cases()),
            'estado' => EstadoVehiculo::Activo,
            'kilometraje' => fake()->numberBetween(0, 180000),
            'fecha_adquisicion' => fake()->dateTimeBetween('-6 years', 'now'),
            'observaciones' => null,
        ];
    }

    public function enMantenimiento(): static
    {
        return $this->state(fn (): array => ['estado' => EstadoVehiculo::EnMantenimiento]);
    }

    public function sinConductor(): static
    {
        return $this->state(fn (): array => ['conductor_id' => null]);
    }

    /**
     * Assign the vehicle to a specific (or new) driver.
     */
    public function paraConductor(?Conductor $conductor = null): static
    {
        return $this->state(fn (): array => [
            'conductor_id' => $conductor ?? Conductor::factory(),
        ]);
    }
}
