<?php

namespace Database\Factories;

use App\Models\ItemEquipamiento;
use App\Models\ReposicionEquipamiento;
use App\Models\Vehiculo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReposicionEquipamiento>
 */
class ReposicionEquipamientoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vehiculo_id' => Vehiculo::factory(),
            'item_equipamiento_id' => fn (): int => ItemEquipamiento::query()->where('aplica_a', 'tracto')->orderBy('id')->firstOrFail()->id,
            'cantidad' => 1,
            'nota' => null,
            'user_id' => null,
        ];
    }
}
