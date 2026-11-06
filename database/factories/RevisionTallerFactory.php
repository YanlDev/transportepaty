<?php

namespace Database\Factories;

use App\Enums\ResultadoTaller;
use App\Models\RevisionTaller;
use App\Models\Vuelta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RevisionTaller>
 */
class RevisionTallerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vuelta_id' => Vuelta::factory(),
            'resultado' => ResultadoTaller::Apto,
            'disponible_estimado' => null,
            'motivo' => null,
            'trabajos' => null,
            'user_id' => null,
        ];
    }

    public function noApta(): static
    {
        return $this->state(fn (): array => [
            'resultado' => ResultadoTaller::NoApto,
            'disponible_estimado' => now()->addDay()->setTime(15, 0),
            'motivo' => 'Cambio de zapatas',
        ]);
    }
}
