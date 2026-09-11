<?php

namespace Database\Factories;

use App\Enums\Moneda;
use App\Models\CuentaBancaria;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CuentaBancaria>
 */
class CuentaBancariaFactory extends Factory
{
    protected $model = CuentaBancaria::class;

    /**
     * @var list<string>
     */
    private const BANCOS = ['BCP', 'BBVA', 'Interbank', 'Scotiabank', 'Banco de la Nación'];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $banco = $this->faker->randomElement(self::BANCOS);

        return [
            'banco' => $banco,
            'alias' => "{$banco} Soles",
            'numero_cuenta' => $this->faker->unique()->numerify('###-#########-#-##'),
            'cci' => $this->faker->numerify('####################'),
            'moneda' => Moneda::Soles,
            'activa' => true,
            'notas' => null,
        ];
    }

    public function dolares(): static
    {
        return $this->state(fn (array $atributos): array => [
            'alias' => "{$atributos['banco']} Dólares",
            'moneda' => Moneda::Dolares,
        ]);
    }

    public function inactiva(): static
    {
        return $this->state(fn (): array => ['activa' => false]);
    }
}
