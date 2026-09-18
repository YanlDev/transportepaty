<?php

use App\Enums\TipoCaja;
use App\Enums\TipoDocumento;
use App\Enums\TipoVehiculo;
use App\Models\Vehiculo;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpSpreadsheet\IOFactory;

beforeEach(function (): void {
    $this->salida = storage_path('framework/testing/vehiculos-'.uniqid().'.xlsx');
});

afterEach(function (): void {
    File::delete($this->salida);
});

/**
 * La hoja como matriz de valores, con las fechas ya formateadas a texto para
 * poder afirmar sobre ellas sin traducir el serial de Excel.
 *
 * @return array<int, array<int, mixed>>
 */
function hojaDeVehiculos(string $ruta): array
{
    return IOFactory::load($ruta)->getActiveSheet()->toArray(null, true, true, false);
}

it('exports only the vehicles matching the caja filter', function (): void {
    Vehiculo::factory()->create(['placa' => 'AAA-111', 'caja' => TipoCaja::Automatica]);
    Vehiculo::factory()->create(['placa' => 'BBB-222', 'caja' => TipoCaja::Mecanica]);
    Vehiculo::factory()->create(['placa' => 'CCC-333', 'tipo' => TipoVehiculo::Carreta, 'caja' => null]);

    $this->artisan('transpaty:exportar-vehiculos', [
        '--caja' => 'automatica',
        '--salida' => $this->salida,
    ])->assertSuccessful();

    $placas = collect(hojaDeVehiculos($this->salida))->skip(1)->pluck(0);

    expect($placas)->toContain('AAA-111')
        ->and($placas)->not->toContain('BBB-222')
        ->and($placas)->not->toContain('CCC-333');
});

it('writes each document expiry in its own column', function (): void {
    $vehiculo = Vehiculo::factory()->create([
        'placa' => 'AAA-111',
        'caja' => TipoCaja::Automatica,
        'marca' => 'MERCEDES BENZ',
    ]);

    $vehiculo->documentos()->create([
        'tipo' => TipoDocumento::Soat,
        'fecha_vencimiento' => '2027-02-15',
    ]);
    $vehiculo->documentos()->create(['tipo' => TipoDocumento::TarjetaPropiedad]);

    $this->artisan('transpaty:exportar-vehiculos', ['--salida' => $this->salida])->assertSuccessful();

    $hoja = hojaDeVehiculos($this->salida);
    $encabezados = $hoja[0];
    $fila = $hoja[1];

    $columnaSoat = array_search('Vence SOAT', $encabezados, true);
    $columnaTarjeta = array_search('Tarjeta de propiedad', $encabezados, true);

    expect($fila[0])->toBe('AAA-111')
        ->and($fila[2])->toBe('MERCEDES BENZ')
        ->and($fila[$columnaTarjeta])->toBe('Cargada')
        // Llega formateada, que es la prueba de que se escribió como fecha de
        // Excel y no como texto: un texto saldría tal cual se guardó.
        ->and($fila[$columnaSoat])->toBe('15/02/2027');
});

/**
 * La columna «Situación» es la que se filtra para saber a qué unidad hay que
 * correr, así que tiene que nombrar el documento y no solo el color.
 */
it('spells out what is wrong in the situacion column', function (): void {
    $alDia = Vehiculo::factory()->create(['placa' => 'AAA-111']);

    foreach ($alDia->tipo->documentosObligatorios() as $tipo) {
        $alDia->documentos()->create([
            'tipo' => $tipo,
            'fecha_vencimiento' => now()->addYear()->toDateString(),
        ]);
    }

    $conProblema = Vehiculo::factory()->create(['placa' => 'BBB-222']);
    $conProblema->documentos()->create([
        'tipo' => TipoDocumento::Soat,
        'fecha_vencimiento' => now()->subDay()->toDateString(),
    ]);

    $this->artisan('transpaty:exportar-vehiculos', ['--salida' => $this->salida])->assertSuccessful();

    $hoja = collect(hojaDeVehiculos($this->salida));
    $encabezados = $hoja->first();
    $columna = array_search('Situación', $encabezados, true);
    $porPlaca = $hoja->skip(1)->keyBy(0);

    expect($porPlaca['AAA-111'][$columna])->toBe('Al día')
        ->and($porPlaca['BBB-222'][$columna])->toContain('SOAT')
        ->and($porPlaca['BBB-222'][$columna])->toContain('Vencido');
});

it('rejects a caja that is not a known one', function (): void {
    $this->artisan('transpaty:exportar-vehiculos', ['--caja' => 'turbo'])->assertFailed();
});

it('says so instead of writing an empty book when nothing matches', function (): void {
    $this->artisan('transpaty:exportar-vehiculos', [
        '--caja' => 'automatica',
        '--salida' => $this->salida,
    ])->expectsOutputToContain('Ningún vehículo')->assertSuccessful();

    expect(File::exists($this->salida))->toBeFalse();
});
