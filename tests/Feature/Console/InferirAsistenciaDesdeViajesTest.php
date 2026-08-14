<?php

use App\Enums\EstadoAsistencia;
use App\Models\Asistencia;
use App\Models\Conductor;
use App\Models\Viaje;

/**
 * @return array<string, mixed>
 */
function datosViaje(Conductor $conductor, string $numeroGr, string $fechaTraslado): array
{
    return [
        'numero_gr' => $numeroGr,
        'fecha_emision' => "{$fechaTraslado} 08:00:00",
        'fecha_traslado' => $fechaTraslado,
        'origen' => 'JULIACA',
        'destino' => 'LIMA',
        'tipo_carga' => 'particular',
        'cliente' => 'MINSUR',
        'destinatario' => 'ALMACEN CENTRAL',
        'peso' => 1000,
        'unidad_peso' => 'KG',
        'placa_tracto' => 'ABC123',
        'conductor_nombre' => $conductor->nombre_completo,
        'conductor_dni' => $conductor->documento,
        'conductor_id' => $conductor->id,
    ];
}

it('marca asistencia para cada día con una GR del conductor', function (): void {
    $conductor = Conductor::factory()->create();
    Viaje::create(datosViaje($conductor, 'EG03-00000001', '2026-07-10'));

    $this->artisan('transpaty:inferir-asistencia', [
        '--desde' => '2026-07-01',
        '--hasta' => '2026-07-31',
    ])->assertSuccessful();

    $asistencia = Asistencia::where('conductor_id', $conductor->id)->where('fecha', '2026-07-10')->first();

    expect($asistencia)->not->toBeNull();
    expect($asistencia->estado)->toBe(EstadoAsistencia::Asistencia);
    expect($asistencia->observaciones)->toBe('Inferido de GR EG03-00000001');
});

it('no pisa un estado ya marcado, sea a mano o por una corrida anterior', function (): void {
    $conductor = Conductor::factory()->create();
    Viaje::create(datosViaje($conductor, 'EG03-00000002', '2026-07-11'));

    Asistencia::create([
        'conductor_id' => $conductor->id,
        'fecha' => '2026-07-11',
        'estado' => EstadoAsistencia::Descanso,
        'observaciones' => 'marcado a mano',
    ]);

    $this->artisan('transpaty:inferir-asistencia', [
        '--desde' => '2026-07-01',
        '--hasta' => '2026-07-31',
    ])->assertSuccessful();

    $asistencia = Asistencia::where('conductor_id', $conductor->id)->where('fecha', '2026-07-11')->first();

    expect($asistencia->estado)->toBe(EstadoAsistencia::Descanso);
    expect(Asistencia::count())->toBe(1);
});

it('correrlo dos veces sobre el mismo rango no duplica nada', function (): void {
    $conductor = Conductor::factory()->create();
    Viaje::create(datosViaje($conductor, 'EG03-00000003', '2026-07-12'));

    $this->artisan('transpaty:inferir-asistencia', ['--desde' => '2026-07-01', '--hasta' => '2026-07-31'])->assertSuccessful();
    $this->artisan('transpaty:inferir-asistencia', ['--desde' => '2026-07-01', '--hasta' => '2026-07-31'])->assertSuccessful();

    expect(Asistencia::count())->toBe(1);
});

it('dry-run no escribe nada', function (): void {
    $conductor = Conductor::factory()->create();
    Viaje::create(datosViaje($conductor, 'EG03-00000004', '2026-07-13'));

    $this->artisan('transpaty:inferir-asistencia', [
        '--desde' => '2026-07-01',
        '--hasta' => '2026-07-31',
        '--dry-run' => true,
    ])->assertSuccessful();

    expect(Asistencia::count())->toBe(0);
});
