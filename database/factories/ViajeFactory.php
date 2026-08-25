<?php

namespace Database\Factories;

use App\Enums\TipoCarga;
use App\Models\Viaje;
use Illuminate\Database\Eloquent\Factories\Factory;

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
        $fecha = fake()->dateTimeBetween('-2 months', 'now');

        return [
            'numero_gr' => 'EG03-'.fake()->unique()->numerify('########'),
            'fecha_emision' => $fecha,
            'fecha_traslado' => $fecha->format('Y-m-d'),
            'origen' => 'AV. INDUSTRIAL 123 - JULIACA - SAN ROMAN - PUNO',
            'destino' => 'CAR. PANAMERICANA KM. 50 - PISCO - PISCO - ICA',
            'tipo_carga' => TipoCarga::Particular,
            'cliente' => strtoupper(fake()->company()),
            'destinatario' => strtoupper(fake()->company()),
            'peso' => fake()->randomFloat(3, 20, 32),
            'unidad_peso' => 'TNE',
            'placa_tracto' => strtoupper(fake()->unique()->bothify('???-###')),
            'conductor_nombre' => strtoupper(fake()->name()),
        ];
    }

    /**
     * Cliente Minsur, tal como llega en la GR real (razón social sin
     * variantes de espaciado). Combinar con `->tipoCarga()` para el
     * desglose por tipo de carga.
     */
    public function deMinsur(): static
    {
        return $this->state(fn (): array => ['cliente' => 'MINSUR S.A.']);
    }

    public function tipoCarga(TipoCarga $tipo): static
    {
        return $this->state(fn (): array => ['tipo_carga' => $tipo]);
    }

    /**
     * Misma placa de tracto y carreta, mismo conductor y mismo día que otro
     * viaje: para simular una sola salida del camión con más de una GR (ver
     * `Viaje::claveGrupoViaje()`).
     */
    public function delMismoViajeQue(Viaje $otro): static
    {
        return $this->state(fn (): array => [
            'fecha_traslado' => $otro->fecha_traslado,
            'placa_tracto' => $otro->placa_tracto,
            'placa_carreta' => $otro->placa_carreta,
            'tracto_id' => $otro->tracto_id,
            'carreta_id' => $otro->carreta_id,
            'conductor_nombre' => $otro->conductor_nombre,
            'conductor_dni' => $otro->conductor_dni,
            'conductor_id' => $otro->conductor_id,
        ]);
    }
}
