<?php

namespace Database\Factories;

use App\Enums\UnidadMedida;
use App\Models\Articulo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Articulo>
 */
class ArticuloFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => 'A-'.fake()->unique()->numerify('#####'),
            'nombre' => mb_strtoupper(fake()->randomElement(['Filtro de aceite', 'Filtro de aire', 'Pastilla de freno', 'Faja de alternador', 'Foco H4', 'Grasa multiuso'])).' '.fake()->unique()->numerify('###'),
            'numero_parte' => fake()->bothify('##??###'),
            'marca' => fake()->randomElement(['Fleetguard', 'Donaldson', 'Bosch', 'Scania']),
            'categoria_articulo_id' => null,
            'unidad' => UnidadMedida::Unidad,
            'unidad_compra' => null,
            'factor_compra' => 1,
            'stock_minimo' => null,
            'ubicacion' => null,
            'activo' => true,
        ];
    }

    /**
     * El aceite: se compra en cilindro de 208 litros y sale por litros.
     */
    public function aceiteEnCilindro(): static
    {
        return $this->state(fn (): array => [
            'nombre' => 'ACEITE 15W40 '.fake()->unique()->numerify('###'),
            'unidad' => UnidadMedida::Litro,
            'unidad_compra' => UnidadMedida::Cilindro,
            'factor_compra' => 208,
        ]);
    }
}
