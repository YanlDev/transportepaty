<?php

use App\Enums\TipoOrdenTrabajo;
use App\Models\OrdenTrabajo;
use App\Models\Vehiculo;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Un registro con la forma de la hoja real: por unidad, una fila por paquete
 * y en cada mes FECHA · T/Serv. · KM · PROX. CAMBIO.
 *
 * @param  list<array{0: string|null, 1: string|null, 2: string, 3?: list<array{0: string, 1: string, 2: float|int}>}>  $filas
 */
function registroMantenimiento(array $filas): string
{
    $libro = new Spreadsheet;
    $hoja = $libro->getActiveSheet()->setTitle('MANTENIMIENTOS EJECUTADOS ');
    $hoja->setCellValue('A1', 'CRONOGRAMA DE MANTENIMIENTO 2026');

    foreach ($filas as $indice => $fila) {
        $numero = $indice + 4;
        $hoja->setCellValue("B{$numero}", $fila[0]);
        $hoja->setCellValue("C{$numero}", $fila[1]);
        $hoja->setCellValue("E{$numero}", $fila[2]);

        foreach ($fila[3] ?? [] as $mes => [$fecha, $tipo, $km]) {
            $columna = 7 + $mes * 4;
            $hoja->setCellValue([$columna, $numero], Date::PHPToExcel(new DateTime($fecha)));
            $hoja->getStyle([$columna, $numero])->getNumberFormat()->setFormatCode('m/d/yyyy');
            $hoja->setCellValue([$columna + 1, $numero], $tipo);
            $hoja->setCellValue([$columna + 2, $numero], $km);
        }
    }

    $ruta = tempnam(sys_get_temp_dir(), 'registro').'.xlsx';
    (new Xlsx($libro))->save($ruta);

    return $ruta;
}

it('imports the services done, taking the package from the row', function (): void {
    $unidad = Vehiculo::factory()->create(['placa' => 'BYM-899', 'marca' => 'SCANIA']);

    $ruta = registroMantenimiento([
        ['BYM899', 'SCANIA', 'X', [['2026-07-02', 'X', 203207]]],
        // «PM» en la fila de la S: es una S.
        [null, null, 'S', [['2026-03-13', 'PM', 176319.4], ['2026-07-19', 'RETARDER', 205000]]],
        [null, null, 'M'],
    ]);

    $this->artisan('mantenimiento:importar-historial', ['archivo' => $ruta])->assertSuccessful();

    $ordenes = OrdenTrabajo::query()->where('vehiculo_id', $unidad->id)->with('servicio')->orderBy('fecha')->get();

    expect($ordenes)->toHaveCount(3)
        ->and($ordenes[0]->servicio->codigo)->toBe('S')
        ->and($ordenes[0]->kilometraje)->toBe(176319)
        ->and($ordenes[1]->servicio->codigo)->toBe('X')
        // El retarder no es un paquete del plan.
        ->and($ordenes[2]->tipo)->toBe(TipoOrdenTrabajo::Correctiva)
        ->and($ordenes[2]->servicio_mantenimiento_id)->toBeNull();
});

it('does not duplicate when run twice, and skips brands without a plan', function (): void {
    Vehiculo::factory()->create(['placa' => 'TCD-854', 'marca' => 'SCANIA']);
    Vehiculo::factory()->create(['placa' => 'ABC-123', 'marca' => 'VOLVO']);

    $ruta = registroMantenimiento([
        ['TCD854', 'SCANIA', 'S', [['2026-05-15', 'S', 299219]]],
        ['ABC123', 'VOLVO', 'S', [['2026-05-15', 'PM', 100000]]],
    ]);

    $this->artisan('mantenimiento:importar-historial', ['archivo' => $ruta])
        ->expectsOutputToContain('Saltadas (marca VOLVO sin plan): 1')
        ->assertSuccessful();
    $this->artisan('mantenimiento:importar-historial', ['archivo' => $ruta])
        ->expectsOutputToContain('Ya estaban: 1')
        ->assertSuccessful();

    expect(OrdenTrabajo::query()->count())->toBe(1);
});

it('saves nothing in seco mode', function (): void {
    Vehiculo::factory()->create(['placa' => 'TCD-854', 'marca' => 'SCANIA']);

    $ruta = registroMantenimiento([['TCD854', 'SCANIA', 'S', [['2026-05-15', 'S', 299219]]]]);

    $this->artisan('mantenimiento:importar-historial', ['archivo' => $ruta, '--seco' => true])
        ->expectsOutputToContain('[En seco] Órdenes importadas: 1')
        ->assertSuccessful();

    expect(OrdenTrabajo::query()->count())->toBe(0);
});
