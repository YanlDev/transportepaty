<?php

namespace Database\Factories;

use App\Models\ItemEquipamiento;
use App\Models\PiezaEquipamiento;
use App\Models\Vehiculo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PiezaEquipamiento>
 */
class PiezaEquipamientoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => 'EX-'.fake()->unique()->numerify('###'),
            'item_equipamiento_id' => fn (): int => ItemEquipamiento::query()->where('por_pieza', true)->orderBy('id')->firstOrFail()->id,
            'vehiculo_id' => Vehiculo::factory(),
            'descripcion' => null,
            'serie' => null,
            'vence_el' => null,
        ];
    }
}
