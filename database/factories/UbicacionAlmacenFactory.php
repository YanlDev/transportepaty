<?php

namespace Database\Factories;

use App\Models\UbicacionAlmacen;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UbicacionAlmacen>
 */
class UbicacionAlmacenFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => 'ESTANTE '.fake()->unique()->bothify('?-##'),
            'descripcion' => null,
        ];
    }
}
