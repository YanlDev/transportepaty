<?php

use App\Services\LectorExcelDisponibilidad;
use Tests\Support\ConstructorXlsx;

/**
 * Ruta temporal para un .xlsx armado a mano en cada test. Se borra sola al
 * terminar el proceso: son archivos de prueba, no hace falta llevar cuenta.
 */
function xlsxTemporal(): string
{
    $ruta = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
    register_shutdown_function(fn () => @unlink($ruta));

    return $ruta;
}

it('detecta la cabecera buscando CODIGO aunque no esté en la fila 2', function (): void {
    $ruta = xlsxTemporal();

    ConstructorXlsx::crear($ruta, [
        1 => ['A' => 'Reporte del día'],
        4 => ['A' => 'CODIGO', 'D' => 'CARRETA'],
        5 => ['A' => 'VEP856', 'D' => 'BWC987'],
    ]);

    $hoja = (new LectorExcelDisponibilidad)->leer($ruta);

    expect($hoja->filaCabecera)->toBe(4)
        ->and($hoja->columnaDe('CODIGO'))->toBe('A')
        ->and($hoja->columnaDe('CARRETA'))->toBe('D')
        ->and($hoja->valor(5, 'CODIGO'))->toBe('VEP856');
});

it('tolera que la etiqueta de cabecera venga con mayúsculas o espacios distintos', function (): void {
    $ruta = xlsxTemporal();

    ConstructorXlsx::crear($ruta, [
        2 => ['A' => 'CODIGO', 'F' => '  Tipo carga '],
        3 => ['A' => 'BJI770', 'F' => 'Particular'],
    ]);

    $hoja = (new LectorExcelDisponibilidad)->leer($ruta);

    expect($hoja->valor(3, 'Tipo Carga'))->toBe('Particular');
});

it('lanza un error claro cuando no encuentra CODIGO', function (): void {
    $ruta = xlsxTemporal();

    ConstructorXlsx::crear($ruta, [
        2 => ['A' => 'PLACA', 'B' => 'CONDUCTOR'],
        3 => ['A' => 'BJI770', 'B' => 'Juan Pérez'],
    ]);

    expect(fn () => (new LectorExcelDisponibilidad)->leer($ruta))
        ->toThrow(RuntimeException::class, 'CODIGO');
});

it('solo cuenta como fila de datos la que tiene algo en CODIGO', function (): void {
    $ruta = xlsxTemporal();

    ConstructorXlsx::crear($ruta, [
        2 => ['A' => 'CODIGO', 'D' => 'CARRETA'],
        3 => ['A' => 'BJI770', 'D' => 'VFK972'],
        4 => ['D' => 'huérfana, sin código'],
        5 => ['A' => 'CAL900', 'D' => 'VFJ974'],
    ]);

    $hoja = (new LectorExcelDisponibilidad)->leer($ruta);

    expect($hoja->numerosDeFilaConDatos())->toBe([3, 5]);
});

it('lee el archivo real de disponibilidad tal como llega por WhatsApp', function (): void {
    $hoja = (new LectorExcelDisponibilidad)->leer(base_path('tests/Fixtures/disponibilidad-real.xlsx'));

    expect($hoja->filaCabecera)->toBe(2)
        ->and($hoja->columnaDe('CODIGO'))->toBe('A')
        ->and($hoja->columnaDe('CARRETA'))->toBe('D')
        ->and($hoja->columnaDe('CONDUCTOR'))->toBe('E')
        ->and($hoja->columnaDe('Tipo Carga'))->toBe('F')
        // La columna que dice «Estado Unidad» es en realidad el cliente.
        ->and($hoja->columnaDe('Estado Unidad'))->toBe('G')
        ->and($hoja->columnaDe('Ruta'))->toBe('H')
        ->and(count($hoja->numerosDeFilaConDatos()))->toBeGreaterThanOrEqual(60);

    $primeraFila = $hoja->numerosDeFilaConDatos()[0];

    expect($hoja->valor($primeraFila, 'CODIGO'))->not->toBeEmpty()
        ->and($hoja->valor($primeraFila, 'Ruta'))->toContain('=>');
});
