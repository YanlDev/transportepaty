<?php

use App\Enums\EstadoAsistencia;
use App\Models\Asistencia;
use App\Models\Conductor;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Arma un .xlsx mínimo con la misma forma que la planilla real: hoja "DIAS
 * LABORADOS", DNI en la columna I, días desde la M, códigos de una sola letra
 * (o "VD") por celda. Fila 7 trae un DNI que no matchea ningún conductor y la
 * columna O de esa fila un código que no está en el mapa, para probar que el
 * importador los reporta en vez de romperse o adivinar.
 */
function planillaDePrueba(string $ruta): void
{
    $libro = new Spreadsheet;
    $hoja = $libro->getActiveSheet();
    $hoja->setTitle('DIAS LABORADOS');

    foreach (['M3' => 'L', 'N3' => 'M', 'O3' => 'X', 'P3' => 'J', 'Q3' => 'V'] as $celda => $valor) {
        $hoja->setCellValue($celda, $valor);
    }

    $hoja->setCellValueExplicit('I5', 'A1111111', DataType::TYPE_STRING);
    $hoja->setCellValue('M5', 1);
    $hoja->setCellValue('N5', 'D');
    $hoja->setCellValue('O5', 'VD');
    $hoja->setCellValue('P5', 'F');
    // Q5 queda en blanco a propósito: sin marcar.

    $hoja->setCellValueExplicit('I6', 'B2222222', DataType::TYPE_STRING);
    $hoja->setCellValue('M6', 1);
    $hoja->setCellValue('N6', 1);
    $hoja->setCellValue('O6', 'ZZ');
    $hoja->setCellValue('P6', 'D');
    $hoja->setCellValue('Q6', 1);

    $hoja->setCellValueExplicit('H7', 'Nadie Conocido', DataType::TYPE_STRING);
    $hoja->setCellValueExplicit('I7', '9999999', DataType::TYPE_STRING);
    $hoja->setCellValue('M7', 1);

    (new Xlsx($libro))->save($ruta);
}

beforeEach(function (): void {
    $this->ruta = tempnam(sys_get_temp_dir(), 'planilla').'.xlsx';
    planillaDePrueba($this->ruta);
});

afterEach(function (): void {
    @unlink($this->ruta);
});

it('importa los 4 códigos reales a los estados correctos', function (): void {
    $a = Conductor::factory()->create(['documento' => 'A1111111']);
    $b = Conductor::factory()->create(['documento' => 'B2222222']);

    $this->artisan('transpaty:importar-asistencias', [
        'archivo' => $this->ruta,
        '--desde' => '2026-05-28',
    ])->assertSuccessful();

    expect(Asistencia::where('conductor_id', $a->id)->where('fecha', '2026-05-28')->first()->estado)
        ->toBe(EstadoAsistencia::Asistencia);
    expect(Asistencia::where('conductor_id', $a->id)->where('fecha', '2026-05-29')->first()->estado)
        ->toBe(EstadoAsistencia::Descanso);
    expect(Asistencia::where('conductor_id', $a->id)->where('fecha', '2026-05-30')->first()->estado)
        ->toBe(EstadoAsistencia::Vacaciones);
    expect(Asistencia::where('conductor_id', $a->id)->where('fecha', '2026-05-31')->first()->estado)
        ->toBe(EstadoAsistencia::Falta);

    // Celda en blanco: sin marcar, no crea fila.
    expect(Asistencia::where('conductor_id', $a->id)->where('fecha', '2026-06-01')->exists())->toBeFalse();

    // Código no reconocido ("ZZ"): no se importa esa celda puntual, pero
    // el resto de la fila de ese conductor sí.
    expect(Asistencia::where('conductor_id', $b->id)->where('fecha', '2026-05-30')->exists())->toBeFalse();
    expect(Asistencia::where('conductor_id', $b->id)->count())->toBe(4);
});

it('no crea nada para un DNI que no matchea ningún conductor', function (): void {
    Conductor::factory()->create(['documento' => 'A1111111']);
    Conductor::factory()->create(['documento' => 'B2222222']);

    $this->artisan('transpaty:importar-asistencias', [
        'archivo' => $this->ruta,
        '--desde' => '2026-05-28',
    ])->assertSuccessful();

    expect(Conductor::where('documento', '9999999')->exists())->toBeFalse();
    expect(Asistencia::whereHas('conductor', fn ($q) => $q->where('documento', '9999999'))->exists())->toBeFalse();
});

it('no duplica ni pisa datos ya existentes al correr dos veces', function (): void {
    $a = Conductor::factory()->create(['documento' => 'A1111111']);
    Conductor::factory()->create(['documento' => 'B2222222']);

    // Un estado ya marcado a mano antes de importar: el import no debe pisarlo.
    Asistencia::create([
        'conductor_id' => $a->id,
        'fecha' => '2026-05-28',
        'estado' => EstadoAsistencia::Vacaciones,
        'observaciones' => 'marcado a mano',
    ]);

    $this->artisan('transpaty:importar-asistencias', ['archivo' => $this->ruta, '--desde' => '2026-05-28'])->assertSuccessful();
    $totalPrimeraVez = Asistencia::count();

    $this->artisan('transpaty:importar-asistencias', ['archivo' => $this->ruta, '--desde' => '2026-05-28'])->assertSuccessful();

    expect(Asistencia::count())->toBe($totalPrimeraVez);
    expect(Asistencia::where('conductor_id', $a->id)->where('fecha', '2026-05-28')->first()->estado)
        ->toBe(EstadoAsistencia::Vacaciones);
});

it('dry-run no escribe nada', function (): void {
    Conductor::factory()->create(['documento' => 'A1111111']);
    Conductor::factory()->create(['documento' => 'B2222222']);

    $this->artisan('transpaty:importar-asistencias', [
        'archivo' => $this->ruta,
        '--desde' => '2026-05-28',
        '--dry-run' => true,
    ])->assertSuccessful();

    expect(Asistencia::count())->toBe(0);
});
