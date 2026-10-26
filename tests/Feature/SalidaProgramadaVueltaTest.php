<?php

use App\Enums\EstadoVuelta;
use App\Enums\OrigenVuelta;
use App\Enums\SentidoTramo;
use App\Enums\TipoCarga;
use App\Enums\TipoVehiculo;
use App\Enums\ZonaRuta;
use App\Models\ParadaVuelta;
use App\Models\Programacion;
use App\Models\Vehiculo;
use App\Models\Viaje;
use App\Models\Vuelta;
use App\Services\AvisoDeSalida;

/**
 * Programar la unidad en la pantalla de Programación es lo que hace nacer
 * el ticket del viaje: la salida es la ida particular de la vuelta.
 */
beforeEach(function (): void {
    $this->tracto = Vehiculo::factory()->create(['tipo' => TipoVehiculo::Tracto]);
});

function salida(Vehiculo $tracto, array $datos = []): Programacion
{
    return Programacion::factory()->create([
        'vehiculo_id' => $tracto->id,
        'fecha' => '2026-10-27',
        'destino' => 'JULIACA',
        'lugar_carga' => 'HUARAL',
        ...$datos,
    ])->fresh();
}

it('opens the trip ticket when a unit is programmed', function (): void {
    $salida = salida($this->tracto);

    $parada = $salida->parada;
    $vuelta = $parada->vuelta;

    expect($vuelta->origen)->toBe(OrigenVuelta::Programacion)
        ->and($vuelta->estado)->toBe(EstadoVuelta::Programada)
        ->and($vuelta->tracto_id)->toBe($this->tracto->id)
        ->and($vuelta->conductor_id)->toBe($salida->conductor_id)
        ->and($vuelta->inicio->toDateString())->toBe('2026-10-27')
        // Lima → Juliaca sube: es el regreso de una vuelta.
        ->and($parada->sentido)->toBe(SentidoTramo::Regreso)
        ->and($parada->zona_origen)->toBe(ZonaRuta::Lima)
        ->and($parada->zona_destino)->toBe(ZonaRuta::Juliaca)
        ->and($parada->lugar_origen)->toBe('Huaral')
        ->and($parada->tipo_carga)->toBe(TipoCarga::Particular)
        ->and($parada->cliente_id)->toBe($salida->cliente_id);

    expect(app(AvisoDeSalida::class)->mensajeParaConductor($salida))
        ->toContain("Ticket: *{$vuelta->codigo}*");
});

it('hooks the departure onto a trip already programmed for those days', function (): void {
    $vuelta = Vuelta::factory()->programada()->create(['tracto_id' => $this->tracto->id, 'inicio' => '2026-10-26']);
    $ida = ParadaVuelta::factory()->create([
        'vuelta_id' => $vuelta->id, 'sentido' => SentidoTramo::Regreso, 'orden' => 1,
        'zona_origen' => ZonaRuta::Lima, 'zona_destino' => ZonaRuta::Juliaca, 'tipo_carga' => TipoCarga::Particular,
    ]);

    $salida = salida($this->tracto);

    expect($salida->parada_vuelta_id)->toBe($ida->id)
        ->and(Vuelta::query()->count())->toBe(1);
});

it('follows the edits of the departure', function (): void {
    $salida = salida($this->tracto);
    $vieja = $salida->parada->vuelta;

    $salida->update(['destino' => 'AREQUIPA']);
    expect($salida->fresh()->parada->zona_destino)->toBe(ZonaRuta::Sur);

    // Otra unidad: la salida pasa a la vuelta de esa unidad y la vieja, que
    // quedó vacía, se va.
    $otro = Vehiculo::factory()->create(['tipo' => TipoVehiculo::Tracto]);
    $salida->update(['vehiculo_id' => $otro->id]);

    expect($salida->fresh()->parada->vuelta->tracto_id)->toBe($otro->id)
        ->and($vieja->fresh())->toBeNull();
});

it('removes the trip with the departure while it has no GR', function (): void {
    $salida = salida($this->tracto);
    $vuelta = $salida->parada->vuelta;

    $salida->delete();

    expect($vuelta->fresh())->toBeNull();
});

it('drops the GR of the departure into its ticket', function (): void {
    $salida = salida($this->tracto);

    $guia = Viaje::factory()->create([
        'tracto_id' => $this->tracto->id,
        'fecha_traslado' => '2026-10-27',
        'fecha_emision' => '2026-10-27',
        'origen' => 'PARCELA 55 - HUARAL - HUARAL - LIMA',
        'destino' => 'JR. SUCRE 415 - JULIACA - SAN ROMAN - PUNO',
        'tipo_carga' => TipoCarga::Particular,
    ])->fresh();

    expect($guia->parada_vuelta_id)->toBe($salida->parada_vuelta_id)
        ->and($salida->parada->vuelta->fresh()->estado)->toBe(EstadoVuelta::EnCurso);
});

it('adds the climb to the trip the unit is already on', function (): void {
    // La unidad bajó con concentrado el 20: anda en esa vuelta.
    Viaje::factory()->create([
        'tracto_id' => $this->tracto->id,
        'fecha_traslado' => '2026-10-20',
        'fecha_emision' => '2026-10-20',
        'origen' => 'SAN RAFAEL - ANTAUTA - MELGAR - PUNO',
        'destino' => 'KM 245 - PARACAS - PISCO - ICA',
        'tipo_carga' => TipoCarga::Concentrado,
    ]);
    $enCurso = Vuelta::query()->sole();

    $salida = salida($this->tracto);

    expect($salida->parada->vuelta->is($enCurso))->toBeTrue()
        ->and($salida->parada->sentido)->toBe(SentidoTramo::Regreso)
        ->and($enCurso->fresh()->origen)->toBe(OrigenVuelta::Programacion)
        ->and(Vuelta::query()->count())->toBe(1);
});
