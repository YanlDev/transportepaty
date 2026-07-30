<?php

use App\Enums\OrigenDato;
use App\Enums\TipoCarga;
use App\Models\Conductor;
use App\Models\EstadoUnidad;
use App\Models\Importacion;
use App\Models\Ubicacion;
use App\Models\Vehiculo;
use App\Services\HojaExcelLeida;
use App\Services\ImportadorDisponibilidad;
use Database\Seeders\UbicacionSeeder;

beforeEach(function (): void {
    $this->seed(UbicacionSeeder::class);
});

/**
 * @param  array<int, array<string, string>>  $filasDeDatos
 */
function hojaCon(array $filasDeDatos): HojaExcelLeida
{
    $columnas = [
        'A' => 'CODIGO', 'B' => 'EMPRESA', 'C' => 'TRACTO', 'D' => 'CARRETA',
        'E' => 'CONDUCTOR', 'F' => 'Tipo Carga', 'G' => 'Estado Unidad',
        'H' => 'Ruta', 'I' => 'Ubicación (09:30 am)', 'U' => 'Observaciones',
    ];

    $filas = [2 => $columnas];

    foreach ($filasDeDatos as $numero => $fila) {
        $filas[$numero] = $fila;
    }

    return new HojaExcelLeida(2, $columnas, $filas);
}

function importar(HojaExcelLeida $hoja, ?Importacion $importacion = null): Importacion
{
    $importacion ??= Importacion::factory()->create();
    (new ImportadorDisponibilidad)->procesar($hoja, $importacion);

    return $importacion->fresh();
}

it('resuelve el tracto y la carreta por placa, ignorando el guion', function (): void {
    Vehiculo::factory()->create(['placa' => 'VEP-856']);
    Vehiculo::factory()->carreta()->create(['placa' => 'BWC-987']);

    $importacion = importar(hojaCon([
        3 => ['A' => 'VEP856', 'D' => 'BWC987'],
    ]));

    $fila = $importacion->filas->first();

    expect($fila->tracto->placa)->toBe('VEP-856')
        ->and($fila->carreta->placa)->toBe('BWC-987')
        ->and($fila->problemas)->toBe([]);
});

it('marca la fila sin tracto para no incluirla', function (): void {
    $importacion = importar(hojaCon([
        3 => ['A' => 'ZZZ999'],
    ]));

    $fila = $importacion->filas->first();

    expect($fila->tracto_id)->toBeNull()
        ->and($fila->incluir)->toBeFalse()
        ->and($fila->puedeAplicarse())->toBeFalse()
        ->and($fila->problemas)->toContain('Tracto «ZZZ999» no está en la flota registrada.');
});

it('no exige conductor: «0» significa sin asignar y no es un problema', function (): void {
    Vehiculo::factory()->create(['placa' => 'BJF934']);

    $fila = importar(hojaCon([3 => ['A' => 'BJF934', 'E' => '0']]))->filas->first();

    expect($fila->conductor_id)->toBeNull()
        ->and($fila->problemas)->toBe([]);
});

it('reconoce al conductor aunque el reporte lo escriba apellidos primero', function (): void {
    Vehiculo::factory()->create(['placa' => 'BJG847']);
    Conductor::factory()->create(['nombres' => 'Marco Antonio', 'apellidos' => 'Perez Casapia']);

    $fila = importar(hojaCon([
        3 => ['A' => 'BJG847', 'E' => 'PEREZ CASAPIA MARCO ANTONIO'],
    ]))->filas->first();

    expect($fila->conductor)->not->toBeNull()
        ->and($fila->conductor->nombres)->toBe('Marco Antonio');
});

it('avisa cuando el conductor del reporte no está entre los activos', function (): void {
    Vehiculo::factory()->create(['placa' => 'BJG847']);

    $fila = importar(hojaCon([
        3 => ['A' => 'BJG847', 'E' => 'ALGUIEN QUE NO EXISTE'],
    ]))->filas->first();

    expect($fila->conductor_id)->toBeNull()
        ->and($fila->problemas)->toContain('Conductor «ALGUIEN QUE NO EXISTE» no se reconoce entre los conductores activos.');
});

it('resuelve el tipo de carga por su etiqueta', function (): void {
    Vehiculo::factory()->create(['placa' => 'BUJ915']);

    $fila = importar(hojaCon([
        3 => ['A' => 'BUJ915', 'F' => 'Materiales'],
    ]))->filas->first();

    expect($fila->tipo_carga)->toBe(TipoCarga::Materiales);
});

it('detecta cuando la columna de carga trae una ruta en vez de un tipo', function (): void {
    // El error de columnas corridas que aparece en los reportes reales.
    Vehiculo::factory()->create(['placa' => 'BJI770']);

    $fila = importar(hojaCon([
        3 => ['A' => 'BJI770', 'F' => 'Lima => Juliaca'],
    ]))->filas->first();

    expect($fila->tipo_carga)->toBeNull()
        ->and($fila->problemas[0])->toContain('parece una columna corrida');
});

it('detecta cuando la columna de cliente trae una ubicación', function (): void {
    Vehiculo::factory()->create(['placa' => 'BUJ860']);

    $fila = importar(hojaCon([
        3 => ['A' => 'BUJ860', 'G' => 'Juliaca'],
    ]))->filas->first();

    expect($fila->problemas)->toContain('La columna de cliente trae una ubicación («Juliaca»): parece una columna corrida.');
});

it('no marca problema cuando la columna de cliente trae Minsur o Particular', function (string $valor): void {
    Vehiculo::factory()->create(['placa' => 'BUJ860']);

    $fila = importar(hojaCon([3 => ['A' => 'BUJ860', 'G' => $valor]]))->filas->first();

    expect($fila->problemas)->toBe([]);
})->with(['Minsur', 'Particular']);

it('separa la ruta en origen y destino y los resuelve contra el catálogo', function (): void {
    Vehiculo::factory()->create(['placa' => 'BYM898']);

    $fila = importar(hojaCon([
        3 => ['A' => 'BYM898', 'H' => 'San Rafael => Pisco'],
    ]))->filas->first();

    expect($fila->origen->codigo)->toBe('san_rafael')
        ->and($fila->destino->codigo)->toBe('pisco');
});

it('avisa cuando la ruta no tiene el formato origen => destino', function (): void {
    Vehiculo::factory()->create(['placa' => 'BYM898']);

    $fila = importar(hojaCon([3 => ['A' => 'BYM898', 'H' => '0']]))->filas->first();

    expect($fila->origen_id)->toBeNull()
        ->and($fila->destino_id)->toBeNull()
        ->and($fila->problemas)->toBe([]);
});

it('resuelve la ubicación actual contra el catálogo con sus alias', function (): void {
    Vehiculo::factory()->create(['placa' => 'CAL900']);

    $fila = importar(hojaCon([
        3 => ['A' => 'CAL900', 'I' => 'U.M San Rafael'],
    ]))->filas->first();

    expect($fila->ubicacion->codigo)->toBe('san_rafael');
});

it('detecta cuando la ubicación en realidad es una novedad de campo', function (string $texto): void {
    Vehiculo::factory()->create(['placa' => 'BUK886']);

    $fila = importar(hojaCon([3 => ['A' => 'BUK886', 'I' => $texto]]))->filas->first();

    expect($fila->ubicacion_id)->toBeNull()
        ->and($fila->problemas[0])->toContain('no es una ubicación');
})->with(['PROGRAMADO', 'NO CONTESTA', 'VACACIONES']);

it('deja la fila en la previsualización sin tocar el estado canónico', function (): void {
    Vehiculo::factory()->create(['placa' => 'VEP856']);

    importar(hojaCon([3 => ['A' => 'VEP856', 'F' => 'Concentrado']]));

    expect(EstadoUnidad::query()->count())->toBe(0);
});

it('confirmar aplica solo las filas incluidas y crea el estado del día', function (): void {
    Vehiculo::factory()->create(['placa' => 'VEP856']);
    Vehiculo::factory()->create(['placa' => 'ZZZ999999']);

    $importacion = importar(hojaCon([
        3 => ['A' => 'VEP856', 'F' => 'Concentrado', 'H' => 'San Rafael => Pisco', 'I' => 'Nazca'],
        4 => ['A' => 'NOEXISTE'],
    ]), Importacion::factory()->create(['fecha' => '2026-07-29']));

    $aplicadas = (new ImportadorDisponibilidad)->confirmar($importacion);

    expect($aplicadas)->toBe(1)
        ->and($importacion->fresh()->estaConfirmada())->toBeTrue();

    $estado = EstadoUnidad::query()->firstOrFail();

    expect($estado->fecha->toDateString())->toBe('2026-07-29')
        ->and($estado->tipo_carga)->toBe(TipoCarga::Concentrado)
        ->and($estado->ubicacion->codigo)->toBe('nazca')
        ->and($estado->origenDe('tipo_carga'))->toBe(OrigenDato::Importado);
});

it('no pisa lo que ya estaba confirmado a mano al reimportar', function (): void {
    $tracto = Vehiculo::factory()->create(['placa' => 'VEP856']);

    $existente = EstadoUnidad::factory()->create([
        'tracto_id' => $tracto->id,
        'fecha' => '2026-07-29',
        'ubicacion_id' => Ubicacion::query()->where('codigo', 'pisco')->value('id'),
    ]);
    $existente->confirmar(['ubicacion_id'])->save();

    $importacion = importar(hojaCon([
        3 => ['A' => 'VEP856', 'I' => 'Nazca'],
    ]), Importacion::factory()->create(['fecha' => '2026-07-29']));

    (new ImportadorDisponibilidad)->confirmar($importacion);

    expect($existente->fresh()->ubicacion->codigo)->toBe('pisco');
});

it('cuenta las filas totales y resueltas al procesar', function (): void {
    Vehiculo::factory()->create(['placa' => 'VEP856']);

    $importacion = importar(hojaCon([
        3 => ['A' => 'VEP856'],
        4 => ['A' => 'NOEXISTE'],
    ]));

    expect($importacion->filas_totales)->toBe(2)
        ->and($importacion->filas_resueltas)->toBe(1);
});

it('conserva el valor crudo de la celda aunque no se pueda resolver', function (): void {
    $fila = importar(hojaCon([3 => ['A' => 'ZZZ999', 'E' => 'ALGUIEN']]))->filas->first();

    expect($fila->crudo['CODIGO'])->toBe('ZZZ999')
        ->and($fila->crudo['CONDUCTOR'])->toBe('ALGUIEN');
});
