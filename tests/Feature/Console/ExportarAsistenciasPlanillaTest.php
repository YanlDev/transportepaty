<?php

use App\Enums\EstadoAsistencia;
use App\Models\Asistencia;
use App\Models\Conductor;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Arma un .xlsx con la misma forma que la planilla real: hoja "DIAS
 * LABORADOS", nombre en H, DNI en I, grilla de días desde la M ya
 * pre-creada vacía (igual que la planilla real, donde cada celda del mes
 * existe de antemano con su estilo, solo sin valor). Una segunda hoja
 * ("OTRA HOJA") sirve para comprobar que exportar no la toca.
 */
function planillaParaExportar(string $ruta): void
{
    $libro = new Spreadsheet;
    $hoja = $libro->getActiveSheet();
    $hoja->setTitle('DIAS LABORADOS');

    foreach (['M3' => 'L', 'N3' => 'M', 'O3' => 'X', 'P3' => 'J'] as $celda => $valor) {
        $hoja->setCellValue($celda, $valor);
    }

    $hoja->setCellValueExplicit('H5', 'PEREZ LOPEZ JUAN', DataType::TYPE_STRING);
    $hoja->setCellValueExplicit('I5', 'A1111111', DataType::TYPE_STRING);
    // La grilla real trae la celda ya creada (vacía, con estilo) antes de
    // marcarse: sin valor, PhpSpreadsheet ni siquiera emite el nodo <c>, así
    // que se le pone un estilo (igual que en la planilla real) para forzarlo.
    foreach (['M5', 'N5', 'O5', 'P5', 'M6', 'N6', 'O6', 'P6'] as $celda) {
        $hoja->getStyle($celda)->getFill()->setFillType(Fill::FILL_SOLID);
    }
    // Una fórmula en medio de la grilla de días: no debería pasar en la
    // realidad, pero si pasa, exportar no debe pisarla.
    $hoja->setCellValue('O5', '=1+1');

    $hoja->setCellValueExplicit('H6', 'SIN CONDUCTOR', DataType::TYPE_STRING);
    $hoja->setCellValueExplicit('I6', '9999999', DataType::TYPE_STRING);

    $otraHoja = $libro->createSheet();
    $otraHoja->setTitle('OTRA HOJA');
    $otraHoja->setCellValue('A1', 'no debe tocarse');

    (new Xlsx($libro))->save($ruta);
}

beforeEach(function (): void {
    $this->ruta = tempnam(sys_get_temp_dir(), 'planilla').'.xlsx';
    planillaParaExportar($this->ruta);
});

afterEach(function (): void {
    @unlink($this->ruta);
});

it('vuelca los 4 estados a los códigos correctos en la hoja', function (): void {
    $conductor = Conductor::factory()->create(['documento' => 'A1111111']);

    Asistencia::create(['conductor_id' => $conductor->id, 'fecha' => '2026-07-28', 'estado' => EstadoAsistencia::Asistencia]);
    Asistencia::create(['conductor_id' => $conductor->id, 'fecha' => '2026-07-29', 'estado' => EstadoAsistencia::Descanso]);
    Asistencia::create(['conductor_id' => $conductor->id, 'fecha' => '2026-07-31', 'estado' => EstadoAsistencia::Vacaciones]);

    $this->artisan('transpaty:exportar-asistencias', [
        'archivo' => $this->ruta,
        '--desde' => '2026-07-28',
    ])->assertSuccessful();

    $hoja = IOFactory::load($this->ruta)->getSheetByName('DIAS LABORADOS');

    // «D»/«VD» quedan como inlineStr —PhpSpreadsheet los lee como RichText—,
    // por eso se compara el valor formateado (texto plano), no getValue().
    expect($hoja->getCell('M5')->getValue())->toBe(1)
        ->and($hoja->getCell('N5')->getFormattedValue())->toBe('D')
        ->and($hoja->getCell('P5')->getFormattedValue())->toBe('VD');
});

it('no pisa una celda que ya trae una fórmula', function (): void {
    $conductor = Conductor::factory()->create(['documento' => 'A1111111']);

    // O5 tiene la fórmula "=1+1" en el fixture; O corresponde al 30/07.
    Asistencia::create(['conductor_id' => $conductor->id, 'fecha' => '2026-07-30', 'estado' => EstadoAsistencia::Falta]);

    $this->artisan('transpaty:exportar-asistencias', [
        'archivo' => $this->ruta,
        '--desde' => '2026-07-28',
    ])->assertSuccessful();

    $hoja = IOFactory::load($this->ruta)->getSheetByName('DIAS LABORADOS');

    expect($hoja->getCell('O5')->getValue())->toBe('=1+1');
});

it('deja intacta cualquier otra hoja del libro', function (): void {
    Conductor::factory()->create(['documento' => 'A1111111']);

    $this->artisan('transpaty:exportar-asistencias', [
        'archivo' => $this->ruta,
        '--desde' => '2026-07-28',
    ])->assertSuccessful();

    $otraHoja = IOFactory::load($this->ruta)->getSheetByName('OTRA HOJA');

    expect($otraHoja->getCell('A1')->getValue())->toBe('no debe tocarse');
});

it('cae a emparejar por nombre completo cuando el DNI no matchea', function (): void {
    $conductor = Conductor::factory()->create([
        'nombres' => 'JUAN',
        'apellidos' => 'PEREZ LOPEZ',
        'documento' => '87654321',
    ]);

    Asistencia::create(['conductor_id' => $conductor->id, 'fecha' => '2026-07-28', 'estado' => EstadoAsistencia::Asistencia]);

    $this->artisan('transpaty:exportar-asistencias', [
        'archivo' => $this->ruta,
        '--desde' => '2026-07-28',
    ])->assertSuccessful();

    $hoja = IOFactory::load($this->ruta)->getSheetByName('DIAS LABORADOS');

    expect($hoja->getCell('M5')->getValue())->toBe(1);
});

it('no escribe nada en dry-run', function (): void {
    $conductor = Conductor::factory()->create(['documento' => 'A1111111']);
    Asistencia::create(['conductor_id' => $conductor->id, 'fecha' => '2026-07-28', 'estado' => EstadoAsistencia::Asistencia]);

    $antes = file_get_contents($this->ruta);

    $this->artisan('transpaty:exportar-asistencias', [
        'archivo' => $this->ruta,
        '--desde' => '2026-07-28',
        '--dry-run' => true,
    ])->assertSuccessful();

    expect(file_get_contents($this->ruta))->toBe($antes);
});
