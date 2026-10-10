<?php

use App\Models\Factura;
use App\Models\Viaje;
use App\Services\LectorFactura;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

/**
 * Fixtures reales: representaciones impresas de facturas de la empresa,
 * descargadas de SOL. Cubren los casos que importan para asociar: una o
 * varias GR citadas, la GR de bienes fiscalizables (serie G010), la
 * detracción calculada sobre el valor referencial y la factura que cobra un
 * período sin citar GR.
 */
beforeEach(function (): void {
    Storage::fake('public');

    foreach (['admin', 'visor', 'contador'] as $rol) {
        Role::findOrCreate($rol, 'web');
    }
});

function facturaPdf(string $nombre): UploadedFile
{
    $ruta = base_path("tests/Fixtures/facturas/{$nombre}.pdf");

    return new UploadedFile($ruta, "{$nombre}.pdf", 'application/pdf', null, true);
}

it('reads every figure exactly as SUNAT printed it', function (): void {
    $campos = (new LectorFactura)->extraerDesdeArchivo(base_path('tests/Fixtures/facturas/factura-crisar-dos-gr.pdf'));

    expect($campos)->toMatchArray([
        'numero' => 'E001-14411',
        'emisor_ruc' => '20364000643',
        'fecha_emision' => '2026-09-28',
        'cliente_razon_social' => 'CRISAR LOGISTICA S.A.C.',
        'cliente_ruc' => '20603930844',
        'moneda' => 'PEN',
        'valor' => 7118.64,
        'igv' => 1281.36,
        'total' => 8400.0,
        'detraccion' => 336.0,
        'neto' => 8064.0,
        // El vencimiento de la cuota impresa: 21 días, no 30.
        'fecha_vencimiento' => '2026-10-19',
        'gr_transportista' => ['EG03-00012429', 'EG03-00012430'],
        'periodo_desde' => null,
    ]);
});

it('reads the GR of bienes fiscalizables', function (): void {
    $campos = (new LectorFactura)->extraerDesdeArchivo(base_path('tests/Fixtures/facturas/factura-bureau-veritas-bienes-fiscalizables.pdf'));

    expect($campos['gr_transportista'])->toBe(['G010-00000141'])
        ->and($campos['cliente_razon_social'])->toBe('BUREAU VERITAS COMMODITIES PERÚ S.A.C.');
});

it('reads the periodo of a factura that cites no GR', function (): void {
    $campos = (new LectorFactura)->extraerDesdeArchivo(base_path('tests/Fixtures/facturas/factura-promart-por-periodo.pdf'));

    expect($campos['gr_transportista'])->toBe([])
        ->and($campos['periodo_desde'])->toBe('2026-08-25')
        ->and($campos['periodo_hasta'])->toBe('2026-09-14')
        // Sin coma de miles en el PDF: «S/ 1940.00».
        ->and($campos['detraccion'])->toBe(1940.0);
});

it('keeps out everyone who does not bill', function (): void {
    actingAs(actorConRol('visor'))
        ->post(route('facturas.importar'), ['archivos' => [facturaPdf('factura-socorro-una-gr')]])
        ->assertForbidden();

    expect(Factura::query()->count())->toBe(0);
});

it('registers the factura and links the GR it cites', function (): void {
    $viajes = collect(['EG03-00012429', 'EG03-00012430'])
        ->map(fn (string $numero) => Viaje::factory()->create(['numero_gr' => $numero]));

    actingAs(actorConRol('contador'))
        ->post(route('facturas.importar'), ['archivos' => [facturaPdf('factura-crisar-dos-gr')]])
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('importacion_facturas.0.numero', 'E001-14411')
        ->assertInertiaFlash('importacion_facturas.0.asociadas', ['EG03-00012429', 'EG03-00012430'])
        ->assertInertiaFlash('importacion_facturas.0.alertas', []);

    $factura = Factura::query()->sole();

    expect($factura->viajes()->pluck('viajes.id')->sort()->values()->all())->toBe($viajes->pluck('id')->sort()->values()->all())
        ->and($factura->desde_pdf)->toBeTrue()
        ->and($factura->fechaVencimiento()->toDateString())->toBe('2026-10-19')
        ->and($factura->getFirstMedia('archivo'))->not->toBeNull();
});

/**
 * San Lorenzo: la detracción se calculó sobre el valor referencial (472) y
 * no sobre el total (463). Vale la impresa.
 */
it('keeps the printed detraccion even when it is not 4% of the total', function (): void {
    actingAs(actorConRol('contador'))
        ->post(route('facturas.importar'), ['archivos' => [facturaPdf('factura-san-lorenzo-valor-referencial')]]);

    $factura = Factura::query()->sole();

    expect([$factura->monto, $factura->total, $factura->detraccion, $factura->neto])
        ->toBe(['9808.00', '11573.44', '472.00', '11101.44']);

    // Cobrar el neto no la recalcula: solo se recalcula si se corrige el flete.
    $factura->update(['fecha_pago' => '2026-10-01']);

    expect($factura->fresh()->detraccion)->toBe('472.00');
});

it('updates a factura cargada a mano with the printed figures without losing its GR', function (): void {
    $viaje = Viaje::factory()->create(['numero_gr' => 'EG03-00012354']);
    $factura = Factura::factory()->create(['numero' => 'E001-14372', 'monto' => 1000, 'observacion' => 'Llamar a Juan']);
    $factura->viajes()->attach($viaje);

    actingAs(actorConRol('contador'))
        ->post(route('facturas.importar'), ['archivos' => [facturaPdf('factura-socorro-una-gr')]])
        ->assertInertiaFlash('importacion_facturas.0.nueva', false);

    $factura->refresh();

    expect(Factura::query()->count())->toBe(1)
        ->and($factura->monto)->toBe('3500.00')
        ->and($factura->neto)->toBe('3965.00')
        ->and($factura->observacion)->toBe('Llamar a Juan')
        ->and($factura->viajes()->count())->toBe(1);
});

it('warns about the GR it cites that are missing and leaves the factura por asociar', function (): void {
    actingAs(actorConRol('contador'))
        ->post(route('facturas.importar'), ['archivos' => [facturaPdf('factura-bureau-veritas-bienes-fiscalizables')]])
        ->assertInertiaFlash('importacion_facturas.0.asociadas', [])
        ->assertInertiaFlash('importacion_facturas.0.alertas', [
            'La GR G010-00000141 no está en el sistema: se asociará sola cuando la subas.',
        ]);

    actingAs(actorConRol('contador'))
        ->get(route('contabilidad.index'))
        ->assertInertia(fn ($page) => $page
            ->has('facturasPorAsociar', 1)
            ->where('facturasPorAsociar.0.numero', 'E001-14220')
            ->where('facturasPorAsociar.0.gr_citadas', ['G010-00000141'])
        );
});

/**
 * La GR que faltaba llega después: la factura que la esperaba se asocia sola
 * y sale de la bandeja.
 */
it('links the waiting factura when its missing GR arrives', function (): void {
    actingAs(actorConRol('contador'))
        ->post(route('facturas.importar'), ['archivos' => [facturaPdf('factura-bureau-veritas-bienes-fiscalizables')]]);

    $viaje = Viaje::factory()->create(['numero_gr' => 'G010-00000141']);

    expect($viaje->facturas()->pluck('numero')->all())->toBe(['E001-14220']);
});

it('does not steal a GR that already has another factura', function (): void {
    $viaje = Viaje::factory()->create(['numero_gr' => 'EG03-00012354']);
    Factura::factory()->create(['numero' => 'E001-99999'])->viajes()->attach($viaje);

    actingAs(actorConRol('contador'))
        ->post(route('facturas.importar'), ['archivos' => [facturaPdf('factura-socorro-una-gr')]])
        ->assertInertiaFlash('importacion_facturas.0.alertas', [
            'La GR EG03-00012354 ya tiene la factura E001-99999: asóciala a mano si también va en esta.',
        ]);

    expect($viaje->facturas()->pluck('numero')->all())->toBe(['E001-99999']);
});

it('proposes the GR of the periodo for a factura that cites none', function (): void {
    $ruc = '20536557858';
    $dentro = Viaje::factory()->create(['numero_gr' => 'EG03-00012257', 'cliente_ruc' => $ruc, 'fecha_traslado' => '2026-08-27']);
    Viaje::factory()->create(['numero_gr' => 'EG03-00012999', 'cliente_ruc' => $ruc, 'fecha_traslado' => '2026-09-20']);
    Viaje::factory()->create(['numero_gr' => 'EG03-00012888', 'cliente_ruc' => '20100136741', 'fecha_traslado' => '2026-08-27']);

    actingAs(actorConRol('contador'))
        ->post(route('facturas.importar'), ['archivos' => [facturaPdf('factura-promart-por-periodo')]])
        ->assertInertiaFlash('importacion_facturas.0.asociadas', []);

    actingAs(actorConRol('contador'))
        ->get(route('contabilidad.index'))
        ->assertInertia(fn ($page) => $page
            ->has('facturasPorAsociar.0.candidatas', 1)
            ->where('facturasPorAsociar.0.candidatas.0.id', $dentro->id)
            ->where('facturasPorAsociar.0.candidatas.0.sugerida', true)
        );
});

it('rejects a factura issued by another company', function (): void {
    config(['transpaty.facturacion.ruc_empresa' => '20100136741']);

    actingAs(actorConRol('contador'))
        ->post(route('facturas.importar'), ['archivos' => [facturaPdf('factura-socorro-una-gr')]])
        ->assertInertiaFlash('importacion_facturas.0.reconocida', false);

    expect(Factura::query()->count())->toBe(0);
});

it('does not take a file that is not a factura', function (): void {
    $gr = new UploadedFile(base_path('tests/Fixtures/guias/gr-minsur-concentrado.pdf'), 'gr.pdf', 'application/pdf', null, true);

    actingAs(actorConRol('contador'))
        ->post(route('facturas.importar'), ['archivos' => [$gr]])
        ->assertInertiaFlash('importacion_facturas.0.reconocida', false);

    expect(Factura::query()->count())->toBe(0);
});

it('links a factura por asociar by hand, from the list or by GR number', function (): void {
    $factura = Factura::factory()->create();
    $marcada = Viaje::factory()->create();
    $escrita = Viaje::factory()->create(['numero_gr' => 'EG03-00012429']);

    actingAs(actorConRol('contador'))
        ->post(route('facturas.asociar', $factura), ['viaje_ids' => [$marcada->id], 'numeros_gr' => ['eg03-12429']])
        ->assertSessionHasNoErrors();

    expect($factura->viajes()->pluck('viajes.id')->sort()->values()->all())
        ->toBe(collect([$marcada->id, $escrita->id])->sort()->values()->all());
});

it('rejects linking by hand a GR that is not in the system', function (): void {
    $factura = Factura::factory()->create();

    actingAs(actorConRol('contador'))
        ->post(route('facturas.asociar', $factura), ['viaje_ids' => [], 'numeros_gr' => ['EG03-404']])
        ->assertSessionHasErrors('numeros_gr');

    expect($factura->viajes()->count())->toBe(0);
});

it('requires at least one GR to link', function (): void {
    $factura = Factura::factory()->create();

    actingAs(actorConRol('contador'))
        ->post(route('facturas.asociar', $factura), ['viaje_ids' => [], 'numeros_gr' => []])
        ->assertSessionHasErrors('viaje_ids');
});

it('does not warn about a factura por período that already has its GR', function (): void {
    $viaje = Viaje::factory()->create();
    Factura::factory()->create(['numero' => 'E001-14444'])->viajes()->attach($viaje);

    actingAs(actorConRol('contador'))
        ->post(route('facturas.importar'), ['archivos' => [facturaPdf('factura-promart-por-periodo')]])
        ->assertInertiaFlash('importacion_facturas.0.alertas', []);

    expect(Factura::query()->sole()->monto)->toBe('41097.01');
});
