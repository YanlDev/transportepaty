<?php

namespace Database\Factories;

use App\Enums\OrigenDato;
use App\Enums\TipoCarga;
use App\Models\Conductor;
use App\Models\EstadoUnidad;
use App\Models\Ubicacion;
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
            'origen_id' => null,
            'destino_id' => null,
            'ubicacion_id' => null,
            'ubicacion_texto' => null,
            'observaciones' => null,
        ];
    }

    /**
     * Un estado con carga y ruta coherentes: se toma la primera ruta declarada
     * para ese tipo de carga y se resuelven sus extremos contra el catálogo, de
     * modo que la fila nazca sin alertas de ruta. La carga particular no
     * declara rutas —varía de viaje en viaje—, así que queda sin origen ni
     * destino a menos que el test los ponga aparte.
     */
    public function conCarga(TipoCarga $carga): static
    {
        return $this->state(function () use ($carga): array {
            $rutas = $carga->rutasValidas();

            if ($rutas === []) {
                return ['tipo_carga' => $carga];
            }

            $ruta = $rutas[0];

            return [
                'tipo_carga' => $carga,
                'origen_id' => $this->ubicacion($ruta['origen']),
                'destino_id' => $this->ubicacion($ruta['destino']),
            ];
        });
    }

    /**
     * Sitúa la unidad en el punto indicado por su código de catálogo.
     */
    public function en(string $codigo): static
    {
        return $this->state(fn (): array => ['ubicacion_id' => $this->ubicacion($codigo)]);
    }

    /**
     * Una ubicación que llegó como texto y no se pudo resolver.
     */
    public function conUbicacionSinResolver(string $texto = 'Grifo del kilómetro 48'): static
    {
        return $this->state(fn (): array => [
            'ubicacion_id' => null,
            'ubicacion_texto' => $texto,
        ]);
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

    /**
     * Resuelve un código del catálogo, creando el punto si el seeder no corrió.
     */
    private function ubicacion(string $codigo): int
    {
        return Ubicacion::query()->firstOrCreate(
            ['codigo' => $codigo],
            ['nombre' => str($codigo)->replace('_', ' ')->title()->value()],
        )->id;
    }
}
