<?php

namespace Database\Factories;

use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Proveedor>
 */
class ProveedorFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ruc' => '20'.fake()->unique()->numerify('#########'),
            'razon_social' => mb_strtoupper(fake()->company()).' S.A.C.',
            'nombre_comercial' => null,
            'contacto' => fake()->name(),
            'telefono' => fake()->numerify('9########'),
            'activo' => true,
        ];
    }
}
