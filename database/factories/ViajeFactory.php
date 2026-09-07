<?php

namespace Database\Factories;

use App\Enums\TipoCarga;
use App\Models\Conductor;
use App\Models\PuntoTraslado;
use App\Models\Vehiculo;
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

    /**
     * Viaje con todo lo que la GRE-T exige: puntos con ubigeo, RUC de las dos
     * partes, tracto con TUC y conductor con licencia. Los viajes importados
     * desde PDF no traen nada de esto, así que sin este estado no hay guía
     * que emitir.
     */
    public function emisible(): static
    {
        return $this->state(fn (): array => [
            'cliente' => 'MINSUR S.A.',
            'cliente_ruc' => '20100136741',
            'destinatario' => 'MINSUR S.A.',
            'destinatario_ruc' => '20100136741',
            'peso' => 10150.000,
            'unidad_peso' => 'KGM',
            'guias_remitente' => [['numero' => 'T012 - 855', 'ruc' => '20100136741']],
            'punto_partida_id' => PuntoTraslado::factory()->create([
                'nombre' => 'Planta Paracas',
                'ubigeo' => '070101',
                'direccion' => 'CAR. PANAMERICANA SUR KM. 238 ZONA INDUSTRIAL',
            ]),
            'punto_llegada_id' => PuntoTraslado::factory()->create([
                'nombre' => 'Mina San Rafael',
                'ubigeo' => '210902',
                'direccion' => 'DESVIO C. JULIACA-MACUSANI KM. 102 ASIENTO MINERO SAN RAFAEL',
            ]),
            'tracto_id' => Vehiculo::factory()->create(['placa' => 'VEP793', 'tuc' => '21M25000279E']),
            'carreta_id' => Vehiculo::factory()->create(['placa' => 'BRI984', 'tuc' => '21M25000102E']),
            'conductor_id' => Conductor::factory()->create([
                'nombres' => 'ROSENDO',
                'apellidos' => 'MAMANI CLEMENCIA',
                'documento' => '01328149',
                'licencia' => 'U01328149',
            ]),
        ]);
    }
}
