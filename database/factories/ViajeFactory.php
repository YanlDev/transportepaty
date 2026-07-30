<?php

namespace Database\Factories;

use App\Enums\TipoCarga;
use App\Models\Conductor;
use App\Models\Ubicacion;
use App\Models\Vehiculo;
use App\Models\Viaje;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Viaje>
 */
class ViajeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $carga = TipoCarga::Concentrado;
        $ruta = $carga->rutasValidas()[0];

        return [
            'tracto_id' => Vehiculo::factory(),
            'carreta_id' => Vehiculo::factory()->carreta(),
            'conductor_id' => Conductor::factory(),
            'tipo_carga' => $carga,
            'origen_id' => $this->ubicacion($ruta['origen']),
            'destino_id' => $this->ubicacion($ruta['destino']),
            'fecha_salida' => fake()->dateTimeBetween('-2 months', '-1 week')->format('Y-m-d'),
            'fecha_llegada' => null,
            'numero_guia_remitente' => null,
            'numero_guia_transportista' => null,
            'observaciones' => null,
        ];
    }

    /**
     * Un viaje con carga y ruta coherentes entre sí.
     */
    public function conCarga(TipoCarga $carga): static
    {
        return $this->state(function () use ($carga): array {
            $ruta = $carga->rutasValidas()[0];

            return [
                'tipo_carga' => $carga,
                'origen_id' => $this->ubicacion($ruta['origen']),
                'destino_id' => $this->ubicacion($ruta['destino']),
            ];
        });
    }

    /**
     * Un viaje ya cerrado, con los días de duración indicados.
     */
    public function completado(int $dias = 3): static
    {
        return $this->state(fn (array $atributos): array => [
            'fecha_llegada' => Carbon::parse($atributos['fecha_salida'])
                ->addDays($dias)
                ->toDateString(),
        ]);
    }

    /**
     * Un viaje con sus dos guías numeradas.
     */
    public function conGuias(
        string $remitente = 'T001-1001',
        string $transportista = 'V001-2001',
    ): static {
        return $this->state(fn (): array => [
            'numero_guia_remitente' => $remitente,
            'numero_guia_transportista' => $transportista,
        ]);
    }

    private function ubicacion(string $codigo): int
    {
        return Ubicacion::query()->firstOrCreate(
            ['codigo' => $codigo],
            ['nombre' => str($codigo)->replace('_', ' ')->title()->value()],
        )->id;
    }
}
