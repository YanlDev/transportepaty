<?php

namespace Database\Factories;

use App\Models\MantenimientoItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MantenimientoItem>
 */
class MantenimientoItemFactory extends Factory
{
    public function definition(): array
    {
        $tipo = fake()->randomElement(['aceite', 'filtro_aire', 'frenos', 'refrigerante']);

        return [
            'nombre' => match ($tipo) {
                'aceite' => 'Cambio de aceite + filtro',
                'filtro_aire' => 'Filtro de aire',
                'frenos' => 'Líquido de frenos',
                'refrigerante' => 'Refrigerante',
                default => 'Servicio',
            },
            'tipo_mantenimiento' => $tipo,
            'costo' => fake()->randomFloat(2, 50, 500),
        ];
    }
}
