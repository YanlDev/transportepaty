<?php

namespace Database\Factories;

use App\Enums\OrigenDato;
use App\Enums\TipoCarga;
use App\Models\Conductor;
use App\Models\EstadoUnidad;
use App\Models\Vehiculo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EstadoUnidad>
 */
class EstadoUnidadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fecha' => fake()->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
            'tracto_id' => Vehiculo::factory(),
            'carreta_id' => Vehiculo::factory()->carreta(),
            'conductor_id' => Conductor::factory(),
            'tipo_carga' => null,
            'origen' => null,
            'destino' => null,
            'ubicacion' => null,
            'observaciones' => null,
        ];
    }

    /**
     * Un estado con carga registrada. Origen/destino/ubicación son texto libre;
     * el test que los necesite los pone aparte con `->create([...])`.
     */
    public function conCarga(TipoCarga $carga): static
    {
        return $this->state(fn (): array => ['tipo_carga' => $carga]);
    }

    /**
     * Sitúa la unidad en el punto indicado.
     */
    public function en(string $ubicacion): static
    {
        return $this->state(fn (): array => ['ubicacion' => $ubicacion]);
    }

    public function sinConductor(): static
    {
        return $this->state(fn (): array => ['conductor_id' => null]);
    }

    /**
     * Marca campos como confirmados a mano, para probar que ni la deducción ni
     * una reimportación los pisan.
     *
     * @param  list<string>  $campos
     */
    public function confirmado(array $campos): static
    {
        return $this->state(fn (): array => [
            'origenes' => array_fill_keys($campos, OrigenDato::Manual->value),
        ]);
    }
}
