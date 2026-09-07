<?php

namespace Database\Factories;

use App\Models\PuntoTraslado;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PuntoTraslado>
 */
class PuntoTrasladoFactory extends Factory
{
    protected $model = PuntoTraslado::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->company(),
            'ubigeo' => (string) fake()->numerify('######'),
            'direccion' => fake()->address(),
            'ruc' => null,
            'cod_local' => null,
            'activo' => true,
        ];
    }

    /**
     * Punto que además es establecimiento anexo declarado de un contribuyente.
     */
    public function conEstablecimiento(string $ruc = '20100136741', string $codLocal = '0001'): static
    {
        return $this->state(fn (): array => [
            'ruc' => $ruc,
            'cod_local' => $codLocal,
        ]);
    }
}
