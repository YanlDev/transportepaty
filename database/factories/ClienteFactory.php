<?php

namespace Database\Factories;

use App\Models\Cliente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cliente>
 */
class ClienteFactory extends Factory
{
    protected $model = Cliente::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $razonSocial = mb_strtoupper($this->faker->company()).' S.A.C.';

        return [
            'ruc' => '20'.$this->faker->unique()->numerify('#########'),
            'razon_social' => $razonSocial,
            'alias' => Cliente::aliasSugerido($razonSocial),
            'contacto' => $this->faker->name(),
            'telefono' => $this->faker->numerify('9########'),
            'email' => $this->faker->safeEmail(),
            'recurrente' => false,
            'activo' => true,
            'notas' => null,
        ];
    }

    public function recurrente(): static
    {
        return $this->state(fn (): array => ['recurrente' => true]);
    }

    public function inactivo(): static
    {
        return $this->state(fn (): array => ['activo' => false]);
    }
}
