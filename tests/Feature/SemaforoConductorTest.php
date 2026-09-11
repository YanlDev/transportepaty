<?php

use App\Enums\TipoDocumentoConductor;
use App\Models\Conductor;
use App\Models\ConductorDocumento;
use App\Services\RelojOperativo;

/**
 * El expediente completo y en regla, para partir de verde y mover una sola
 * pieza por prueba.
 *
 * @param  array<string, string|null>  $vencimientos
 */
function expedienteConductor(array $vencimientos = []): Conductor
{
    $conductor = Conductor::factory()->create();
    $lejos = RelojOperativo::fechaDeHoy()->addYears(3)->toDateString();

    foreach (TipoDocumentoConductor::obligatorios() as $tipo) {
        if (array_key_exists($tipo->value, $vencimientos) && $vencimientos[$tipo->value] === 'sin') {
            continue;
        }

        ConductorDocumento::create([
            'conductor_id' => $conductor->id,
            'tipo' => $tipo,
            'fecha_vencimiento' => $vencimientos[$tipo->value] ?? $lejos,
        ]);
    }

    return $conductor->fresh(['documentos']);
}

function ayer(): string
{
    return RelojOperativo::fechaDeHoy()->subDay()->toDateString();
}

it('deja en verde al conductor con todo el expediente vigente', function (): void {
    expect(expedienteConductor()->estadoDocumental()['semaforo'])->toBe('verde');
});

/**
 * El punto del cambio: un DNI caducado no inhabilita para conducir, así que
 * avisa en ámbar en vez de sacar al conductor de la programación.
 */
it('pone en ámbar y no en rojo al conductor con el DNI caducado', function (): void {
    $estado = expedienteConductor([TipoDocumentoConductor::Dni->value => ayer()])->estadoDocumental();

    expect($estado['semaforo'])->toBe('ambar')
        // Sigue apareciendo como vencido: el semáforo no se pone rojo, pero la
        // ficha tiene que mostrar que el documento caducó.
        ->and($estado['vencidos'])->toBe(['DNI']);
});

it('pone en rojo al conductor con la licencia de conducir vencida', function (): void {
    $estado = expedienteConductor([
        TipoDocumentoConductor::LicenciaConducir->value => ayer(),
    ])->estadoDocumental();

    expect($estado['semaforo'])->toBe('rojo')
        ->and($estado['vencidos'])->toBe(['Licencia de conducir A-IIIc']);
});

/**
 * Que el DNI no bloquee al caducar no lo saca del semáforo: si la licencia
 * también venció, manda el rojo.
 */
it('manda el rojo cuando además de caducar el DNI venció la licencia', function (): void {
    $estado = expedienteConductor([
        TipoDocumentoConductor::Dni->value => ayer(),
        TipoDocumentoConductor::LicenciaConducir->value => ayer(),
    ])->estadoDocumental();

    expect($estado['semaforo'])->toBe('rojo');
});

/**
 * Faltar es otra cosa que caducar: el papel tiene que estar en el expediente,
 * y un DNI ausente sigue siendo rojo.
 */
it('pone en rojo al conductor sin DNI cargado', function (): void {
    $estado = expedienteConductor([TipoDocumentoConductor::Dni->value => 'sin'])->estadoDocumental();

    expect($estado['semaforo'])->toBe('rojo')
        ->and($estado['faltantes'])->toBe(['DNI']);
});

it('sabe qué documentos inhabilitan al vencer y cuáles no', function (): void {
    expect(TipoDocumentoConductor::Dni->vencimientoInhabilita())->toBeFalse()
        ->and(TipoDocumentoConductor::LicenciaConducir->vencimientoInhabilita())->toBeTrue()
        ->and(TipoDocumentoConductor::LicenciaEspecial->vencimientoInhabilita())->toBeTrue();
});
