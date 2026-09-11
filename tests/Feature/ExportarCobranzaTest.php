<?php

use App\Enums\Moneda;
use App\Models\CuentaBancaria;
use App\Models\Factura;
use App\Models\Viaje;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    foreach (['admin', 'visor', 'conductor', 'contador'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

/**
 * Descarga el .xlsx y devuelve su hoja como una matriz de valores, para poder
 * afirmar sobre el contenido y no solo sobre los encabezados de la respuesta.
 *
 * @return array<int, array<int, mixed>>
 */
function hojaExportada(string $url): array
{
    $respuesta = test()->get($url);

    $respuesta->assertSuccessful();

    // La respuesta es un `BinaryFileResponse`: en una petición de prueba nunca
    // se envía, así que el archivo temporal sigue en disco y se lee de ahí.
    // `formatData: false` para que las fechas y los montos lleguen como número
    // y no como el texto ya formateado.
    $ruta = $respuesta->baseResponse->getFile()->getPathname();

    return IOFactory::load($ruta)->getActiveSheet()->toArray(null, true, false, false);
}

it('redirects guests to login', function (): void {
    $this->get(route('contabilidad.exportar'))->assertRedirect(route('login'));
});

it('lets the admin and the contador export, and keeps everyone else out', function (): void {
    actingAs(actorConRol('admin'))->get(route('contabilidad.exportar'))->assertSuccessful();
    actingAs(actorConRol('contador'))->get(route('contabilidad.exportar'))->assertSuccessful();

    // La misma regla que la tabla: el visor no ve montos, ni en pantalla ni
    // en un archivo.
    actingAs(actorConRol('visor'))->get(route('contabilidad.exportar'))->assertForbidden();
    actingAs(actorConRol('conductor'))->get(route('contabilidad.exportar'))->assertForbidden();
});

it('sends an xlsx as a download', function (): void {
    Viaje::factory()->create();

    actingAs(actorConRol('admin'))
        ->get(route('contabilidad.exportar'))
        ->assertSuccessful()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->assertDownload();
});

it('writes every column of the table, with the money as a number', function (): void {
    $cuenta = CuentaBancaria::factory()->create(['alias' => 'BCP Soles']);

    $viaje = Viaje::factory()->create([
        'numero_gr' => 'EG03-00012413',
        'fecha_traslado' => '2026-09-11',
        'cliente' => 'MINSUR S.A.',
        'destinatario' => 'MINSUR S.A.',
        'placa_tracto' => 'VEP-897',
        'placa_carreta' => 'BCY-981',
        'conductor_nombre' => 'PUMA CALLOCONDO SALOMON',
        'peso' => 23.76,
        'guias_remitente' => [
            ['numero' => 'T012-899', 'ruc' => '20100000001'],
            ['numero' => 'T012-901', 'ruc' => '20100000001'],
        ],
    ]);

    $factura = Factura::factory()->create([
        'numero' => 'F001-00042',
        'fecha_emision' => '2026-09-12',
        'monto' => 4500.50,
        'moneda' => Moneda::Soles,
        'fecha_pago' => null,
        'cuenta_bancaria_id' => $cuenta->id,
        'observacion' => 'A 30 días',
    ]);

    $viaje->update(['factura_id' => $factura->id]);

    actingAs(actorConRol('admin'));

    $hoja = hojaExportada(route('contabilidad.exportar'));

    expect($hoja[0])->toBe([
        'Fecha', 'N° GR', 'GR remitente', 'Tracto', 'Carreta', 'Conductor',
        'Cliente', 'Destinatario', 'Origen', 'Destino', 'Tipo de carga',
        'Peso (TNE)', 'Estado', 'N° factura', 'Fecha emisión', 'Monto',
        'Moneda', 'Fecha pago', 'Días vencida', 'Cuenta', 'Observación',
    ]);

    $fila = $hoja[1];

    expect($fila[1])->toBe('EG03-00012413')
        // Las dos GR del remitente apiladas en una celda, como en pantalla.
        ->and($fila[2])->toBe("T012-899\nT012-901")
        ->and($fila[3])->toBe('VEP-897')
        ->and($fila[4])->toBe('BCY-981')
        ->and($fila[5])->toBe('PUMA CALLOCONDO SALOMON')
        ->and($fila[6])->toBe('MINSUR S.A.')
        // Número y no texto: sobre esta columna se hacen sumas.
        ->and($fila[11])->toBe(23.76)
        ->and($fila[13])->toBe('F001-00042')
        ->and($fila[15])->toBe(4500.5)
        ->and($fila[16])->toBe('PEN')
        ->and($fila[19])->toBe('BCP Soles')
        ->and($fila[20])->toBe('A 30 días');
});

it('writes the weight in tonnes even when the guide came in kilos', function (): void {
    // Las dos unidades que trae la GR. Sin convertir, la columna mezclaría
    // 23.755 con 23755 y su suma no significaría nada.
    Viaje::factory()->create([
        'numero_gr' => 'EG03-EN-KILOS',
        'fecha_traslado' => '2026-09-11',
        'peso' => 23755,
        'unidad_peso' => 'KGM',
    ]);

    Viaje::factory()->create([
        'numero_gr' => 'EG03-EN-TONELADAS',
        'fecha_traslado' => '2026-09-10',
        'peso' => 30.5,
        'unidad_peso' => 'TNE',
    ]);

    actingAs(actorConRol('admin'));

    $hoja = hojaExportada(route('contabilidad.exportar'));

    expect($hoja[1][1])->toBe('EG03-EN-KILOS')
        ->and($hoja[1][11])->toBe(23.755)
        ->and($hoja[2][1])->toBe('EG03-EN-TONELADAS')
        ->and($hoja[2][11])->toBe(30.5);
});

it('writes the dates as real dates, not text', function (): void {
    Viaje::factory()->create(['fecha_traslado' => '2026-09-11']);

    actingAs(actorConRol('admin'));

    $hoja = hojaExportada(route('contabilidad.exportar'));

    // 46276 es el serial de Excel para el 11-09-2026. Escrita como texto, la
    // columna no se ordena ni se filtra por rango, que es para lo que se baja
    // este archivo.
    expect($hoja[1][0])->toBe(46276.0);
});

it('leaves the billing columns empty when the trip has no invoice', function (): void {
    Viaje::factory()->create();

    actingAs(actorConRol('admin'));

    $hoja = hojaExportada(route('contabilidad.exportar'));

    expect($hoja[1][12])->toBe('Sin facturar')
        ->and($hoja[1][13])->toBeNull()
        ->and($hoja[1][15])->toBeNull()
        ->and($hoja[1][19])->toBeNull();
});

it('exports only what the filters leave, not the whole table', function (): void {
    Viaje::factory()->create(['fecha_traslado' => '2026-09-11', 'cliente' => 'MINSUR S.A.']);
    Viaje::factory()->create(['fecha_traslado' => '2026-08-15', 'cliente' => 'MINSUR S.A.']);
    Viaje::factory()->create(['fecha_traslado' => '2026-09-12', 'cliente' => 'OTRO CLIENTE']);

    actingAs(actorConRol('admin'));

    $hoja = hojaExportada(route('contabilidad.exportar', [
        'cliente' => 'MINSUR S.A.',
        'desde' => '2026-09-01',
        'hasta' => '2026-09-30',
    ]));

    // Solo el viaje de Minsur dentro del rango: uno, más el encabezado.
    expect($hoja)->toHaveCount(2)
        ->and($hoja[1][6])->toBe('MINSUR S.A.');
});

it('exports past the first page, not just the 50 rows on screen', function (): void {
    Viaje::factory()->count(55)->create();

    actingAs(actorConRol('admin'));

    $hoja = hojaExportada(route('contabilidad.exportar'));

    expect($hoja)->toHaveCount(56);
});

it('writes just the header when nothing matches the filters', function (): void {
    Viaje::factory()->create(['cliente' => 'MINSUR S.A.']);

    actingAs(actorConRol('admin'));

    $hoja = hojaExportada(route('contabilidad.exportar', ['cliente' => 'NO EXISTE']));

    expect($hoja)->toHaveCount(1)
        ->and($hoja[0][0])->toBe('Fecha');
});

it('keeps the same order as the table: fecha, then GR correlativo', function (): void {
    foreach (['EG03-00012410', 'EG03-00012414', 'EG03-00012409'] as $numero) {
        Viaje::factory()->create([
            'numero_gr' => $numero,
            'fecha_traslado' => '2026-09-11',
        ]);
    }

    Viaje::factory()->create([
        'numero_gr' => 'EG03-00012500',
        'fecha_traslado' => '2026-09-10',
    ]);

    actingAs(actorConRol('admin'));

    $hoja = hojaExportada(route('contabilidad.exportar'));

    expect(array_column(array_slice($hoja, 1), 1))->toBe([
        'EG03-00012414',
        'EG03-00012410',
        'EG03-00012409',
        'EG03-00012500',
    ]);
});
