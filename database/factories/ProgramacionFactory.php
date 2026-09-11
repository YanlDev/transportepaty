<?php

namespace Database\Factories;

use App\Models\Cliente;
use App\Models\Conductor;
use App\Models\Programacion;
use App\Models\Vehiculo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Programacion>
 */
class ProgramacionFactory extends Factory
{
    protected $model = Programacion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fecha' => $this->faker->dateTimeBetween('-1 month', '+1 week')->format('Y-m-d'),
            'vehiculo_id' => Vehiculo::factory(),
            'conductor_id' => Conductor::factory(),
            'cliente_id' => Cliente::factory(),
            'destino' => $this->faker->randomElement(['JULIACA', 'ILAVE', 'PUNO', 'AREQUIPA', 'TACNA']),
        ];
    }

    public function elDia(string $fecha): static
    {
        return $this->state(fn (): array => ['fecha' => $fecha]);
    }
}
