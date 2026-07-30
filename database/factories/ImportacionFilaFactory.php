<?php

namespace Database\Factories;

use App\Models\Importacion;
use App\Models\ImportacionFila;
use App\Models\Vehiculo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportacionFila>
 */
class ImportacionFilaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tracto = Vehiculo::factory()->create();

        return [
            'importacion_id' => Importacion::factory(),
            'numero_fila' => fake()->unique()->numberBetween(3, 63),
            'crudo' => ['CODIGO' => $tracto->placa],
            'tracto_id' => $tracto->id,
            'problemas' => [],
            'incluir' => true,
        ];
    }

    /**
     * Una fila sin tracto encontrado: no se puede aplicar.
     */
    public function sinTracto(): static
    {
        return $this->state(fn (): array => [
            'crudo' => ['CODIGO' => 'ZZZ999'],
            'tracto_id' => null,
            'problemas' => ['Tracto «ZZZ999» no está en la flota registrada.'],
            'incluir' => false,
        ]);
    }
}
