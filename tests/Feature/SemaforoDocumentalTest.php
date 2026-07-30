<?php

use App\Enums\TipoDocumento;
use App\Enums\TipoVehiculo;
use App\Models\Vehiculo;

/**
 * Carga los documentos indicados en el vehículo y devuelve su semáforo.
 *
 * @param  array<string, string|null>  $documentos  tipo => fecha de vencimiento
 * @return array{semaforo: string, faltantes: list<string>, vencidos: list<string>, por_vencer: list<string>}
 */
function estadoDocumentalCon(Vehiculo $vehiculo, array $documentos): array
{
    foreach ($documentos as $tipo => $vence) {
        $vehiculo->documentos()->create([
            'tipo' => $tipo,
            'fecha_vencimiento' => $vence,
        ]);
    }

    return $vehiculo->load('documentos')->estadoDocumental();
}

/**
 * Los cinco obligatorios del tracto, todos vigentes por un año.
 *
 * @return array<string, string>
 */
function documentosAlDia(TipoVehiculo $tipo): array
{
    $documentos = [];

    foreach ($tipo->documentosObligatorios() as $obligatorio) {
        $documentos[$obligatorio->value] = now()->addYear()->format('Y-m-d');
    }

    return $documentos;
}

it('exige cinco documentos al tracto y cuatro a la carreta', function (): void {
    $tracto = array_map(
        fn (TipoDocumento $tipo): string => $tipo->value,
        TipoVehiculo::Tracto->documentosObligatorios(),
    );
    $carreta = array_map(
        fn (TipoDocumento $tipo): string => $tipo->value,
        TipoVehiculo::Carreta->documentosObligatorios(),
    );

    expect($tracto)->toBe([
        'tarjeta_propiedad',
        'soat',
        'revision_tecnica_carga',
        'habilitacion_mtc',
        'matpel',
    ]);

    // La carreta es remolcada: el SOAT lo lleva la unidad motriz.
    expect($carreta)->toBe([
        'tarjeta_propiedad',
        'revision_tecnica_carga',
        'habilitacion_mtc',
        'matpel',
    ]);
});

it('deja fuera del semáforo los documentos sueltos', function (): void {
    expect(TipoDocumento::Otro->esObligatorio())->toBeFalse()
        ->and(TipoDocumento::Soat->esObligatorio())->toBeTrue()
        ->and(TipoVehiculo::Tracto->documentosAplicables())
        ->toContain(TipoDocumento::Otro)
        ->and(TipoVehiculo::Tracto->documentosObligatorios())
        ->not->toContain(TipoDocumento::Otro);
});

it('pone en verde el vehículo con todo cargado y vigente', function (): void {
    $vehiculo = Vehiculo::factory()->create();

    $estado = estadoDocumentalCon($vehiculo, documentosAlDia(TipoVehiculo::Tracto));

    expect($estado['semaforo'])->toBe('verde')
        ->and($estado['faltantes'])->toBe([])
        ->and($estado['vencidos'])->toBe([])
        ->and($estado['por_vencer'])->toBe([]);
});

it('no exige SOAT a la carreta para ponerla en verde', function (): void {
    $carreta = Vehiculo::factory()->carreta()->create();

    $estado = estadoDocumentalCon($carreta, documentosAlDia(TipoVehiculo::Carreta));

    expect($estado['semaforo'])->toBe('verde');
});

it('pone en rojo el tracto al que le falta el SOAT', function (): void {
    $tracto = Vehiculo::factory()->create();
    $documentos = documentosAlDia(TipoVehiculo::Tracto);
    unset($documentos[TipoDocumento::Soat->value]);

    $estado = estadoDocumentalCon($tracto, $documentos);

    expect($estado['semaforo'])->toBe('rojo')
        ->and($estado['faltantes'])->toBe(['SOAT']);
});

it('cuenta los documentos vencidos', function (): void {
    $vehiculo = Vehiculo::factory()->create();
    $documentos = documentosAlDia(TipoVehiculo::Tracto);
    $documentos[TipoDocumento::Soat->value] = now()->subDay()->format('Y-m-d');
    $documentos[TipoDocumento::Matpel->value] = now()->subMonth()->format('Y-m-d');

    $estado = estadoDocumentalCon($vehiculo, $documentos);

    expect($estado['semaforo'])->toBe('rojo')
        ->and($estado['vencidos'])->toHaveCount(2)
        ->and($estado['vencidos'])->toContain('SOAT')
        ->and($estado['faltantes'])->toBe([]);
});

it('pone en ámbar lo que vence dentro de los treinta días', function (): void {
    $vehiculo = Vehiculo::factory()->create();
    $documentos = documentosAlDia(TipoVehiculo::Tracto);
    $documentos[TipoDocumento::HabilitacionMtc->value] = now()->addDays(10)->format('Y-m-d');

    $estado = estadoDocumentalCon($vehiculo, $documentos);

    expect($estado['semaforo'])->toBe('ambar')
        ->and($estado['por_vencer'])->toBe(['TUC (habilitación MTC)'])
        ->and($estado['vencidos'])->toBe([]);
});

it('trata como vigente el documento sin fecha de vencimiento', function (): void {
    $vehiculo = Vehiculo::factory()->create();
    $documentos = documentosAlDia(TipoVehiculo::Tracto);
    // La tarjeta de propiedad no caduca: basta con tenerla cargada.
    $documentos[TipoDocumento::TarjetaPropiedad->value] = null;

    $estado = estadoDocumentalCon($vehiculo, $documentos);

    expect($estado['semaforo'])->toBe('verde');
});

it('el vencimiento manda sobre el próximo a vencer', function (): void {
    $vehiculo = Vehiculo::factory()->create();
    $documentos = documentosAlDia(TipoVehiculo::Tracto);
    $documentos[TipoDocumento::Soat->value] = now()->subDay()->format('Y-m-d');
    $documentos[TipoDocumento::Matpel->value] = now()->addDays(5)->format('Y-m-d');

    $estado = estadoDocumentalCon($vehiculo, $documentos);

    expect($estado['semaforo'])->toBe('rojo')
        ->and($estado['por_vencer'])->toHaveCount(1);
});

it('pone en rojo el vehículo sin ningún documento', function (): void {
    $vehiculo = Vehiculo::factory()->create();

    $estado = estadoDocumentalCon($vehiculo, []);

    expect($estado['semaforo'])->toBe('rojo')
        ->and($estado['faltantes'])->toHaveCount(5);
});

it('lista cada documento obligatorio con su situación', function (): void {
    $vehiculo = Vehiculo::factory()->create();
    $documentos = documentosAlDia(TipoVehiculo::Tracto);
    $documentos[TipoDocumento::Soat->value] = now()->subDay()->format('Y-m-d');
    $documentos[TipoDocumento::Matpel->value] = now()->addDays(5)->format('Y-m-d');
    unset($documentos[TipoDocumento::HabilitacionMtc->value]);

    $estado = estadoDocumentalCon($vehiculo, $documentos);

    $porTipo = collect($estado['documentos'])->keyBy('tipo');

    expect($estado['documentos'])->toHaveCount(5)
        ->and($porTipo['tarjeta_propiedad']['estado'])->toBe('vigente')
        ->and($porTipo['soat']['estado'])->toBe('vencido')
        ->and($porTipo['matpel']['estado'])->toBe('por_vencer')
        ->and($porTipo['habilitacion_mtc']['estado'])->toBe('faltante')
        ->and($porTipo['revision_tecnica_carga']['estado'])->toBe('vigente');
});

it('deja sin fecha el documento que falta y trae la del que existe', function (): void {
    $vehiculo = Vehiculo::factory()->create();
    $vence = now()->addMonths(6)->format('Y-m-d');

    $estado = estadoDocumentalCon($vehiculo, [
        TipoDocumento::Soat->value => $vence,
    ]);

    $porTipo = collect($estado['documentos'])->keyBy('tipo');

    expect($porTipo['soat']['vence'])->toBe($vence)
        ->and($porTipo['matpel']['vence'])->toBeNull()
        ->and($porTipo['soat']['abreviatura'])->toBe('SOAT')
        ->and($porTipo['habilitacion_mtc']['abreviatura'])->toBe('TUC')
        ->and($porTipo['soat']['estado_label'])->toBe('Vigente')
        ->and($porTipo['matpel']['estado_label'])->toBe('Sin cargar');
});

it('la carreta no lista el SOAT entre sus documentos', function (): void {
    $carreta = Vehiculo::factory()->carreta()->create();

    $estado = estadoDocumentalCon($carreta, documentosAlDia(TipoVehiculo::Carreta));

    expect(collect($estado['documentos'])->pluck('tipo')->all())
        ->toBe([
            'tarjeta_propiedad',
            'revision_tecnica_carga',
            'habilitacion_mtc',
            'matpel',
        ]);
});
