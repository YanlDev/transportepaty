<?php

use App\Models\Factura;
use App\Models\Viaje;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    Role::findOrCreate('admin', 'web');
});

/**
 * En la cobranza la GR se baja de un clic desde su propio número, en vez de
 * abrirse en un visor desde junto al peso.
 */
it('ofrece descargar la GR junto a su número en la cobranza', function (string $dispositivo): void {
    actingAs(actorConRol('admin'));
    $viaje = Viaje::factory()->create(['numero_gr' => 'EG03-00012759']);
    $viaje->addMedia(UploadedFile::fake()->createWithContent('20364000643-31-EG03-12759.pdf', '%PDF-1.4 prueba'))
        ->toMediaCollection('archivo');

    $pagina = visit('/contabilidad');
    $pagina = $dispositivo === 'celular' ? $pagina->on()->mobile() : $pagina->on()->desktop();

    $pagina
        ->assertPresent('a[download][aria-label="Descargar la GR EG03-00012759"]')
        ->assertAttributeContains('a[aria-label="Descargar la GR EG03-00012759"]', 'href', '20364000643-31-EG03-12759.pdf')
        ->assertMissing('[aria-label="Vista rápida de la GR EG03-00012759"]')
        ->assertMissing('[aria-label="Copiar N° GR"]')
        ->assertNoJavaScriptErrors();
})->with(['escritorio', 'celular']);

it('deja el botón de descarga deshabilitado si la GR no tiene PDF', function (): void {
    actingAs(actorConRol('admin'));
    Viaje::factory()->create(['numero_gr' => 'EG03-00012760']);

    visit('/contabilidad')
        ->on()->desktop()
        ->assertPresent('button[disabled][aria-label="La GR EG03-00012760 no tiene PDF"]')
        ->assertNoJavaScriptErrors();
});

it('confirma el copiado del N° GR con un ✓ que se borra solo', function (): void {
    actingAs(actorConRol('admin'));
    Viaje::factory()->create(['numero_gr' => 'EG03-00012761']);

    visit('/contabilidad')
        ->on()->desktop()
        ->click('[title="N° GR: EG03-00012761"]')
        ->assertPresent('[aria-label="N° GR copiado"]')
        ->wait(2.5)
        ->assertMissing('[aria-label="N° GR copiado"]')
        ->assertPresent('[aria-label="Copiar N° GR"]')
        ->assertNoJavaScriptErrors();
});

it('abrevia el estado en la tabla con colores de semáforo y deja el nombre en el tooltip', function (): void {
    actingAs(actorConRol('admin'));
    Viaje::factory()->create(['numero_gr' => 'EG03-00012762']);
    $porCobrar = Viaje::factory()->create(['numero_gr' => 'EG03-00012763']);
    Factura::factory()->create(['numero' => 'E001-15000', 'fecha_pago' => null, 'fecha_emision' => now()->subDays(45)])
        ->viajes()->attach($porCobrar);

    visit('/contabilidad')
        ->on()->desktop()
        ->assertPresent('[title="Sin facturar"] .bg-red-50')
        ->assertSeeIn('[title="Sin facturar"]', 'S/F')
        ->assertPresent('[title="Vencida hace 15 días"] .bg-red-50')
        ->assertSeeIn('[title="Vencida hace 15 días"]', '15d')
        ->assertNoJavaScriptErrors();
});

it('suma otra factura a un viaje desde las acciones de la fila', function (): void {
    actingAs(actorConRol('admin'));
    $viaje = Viaje::factory()->create(['numero_gr' => 'EG03-00012764']);
    Factura::factory()->create(['numero' => 'E001-15001'])->viajes()->attach($viaje);

    visit('/contabilidad')
        ->on()->desktop()
        ->assertDontSee('+ otra factura')
        ->click('[aria-label="Registrar otra factura para la GR EG03-00012764"]')
        ->assertSee('Otra factura para la GR EG03-00012764')
        ->type('input[placeholder="F001-00123"]', 'e001-15002')
        ->press('Registrar factura')
        ->assertSee('Factura registrada sobre 1 viaje.')
        ->assertNoJavaScriptErrors();

    expect($viaje->facturas()->pluck('numero')->sort()->values()->all())
        ->toBe(['E001-15001', 'E001-15002']);
});

it('muestra el flete descompuesto y el cobro de la detracción', function (): void {
    actingAs(actorConRol('admin'));
    $viaje = Viaje::factory()->create(['numero_gr' => 'EG03-00012765']);
    Factura::factory()->create(['numero' => 'E001-14411', 'monto' => null, 'total' => 8400])
        ->viajes()->attach($viaje);

    visit('/contabilidad')
        ->on()->desktop()
        ->assertSee('Neto a pagar')
        ->assertSee('7,118.64')
        ->assertSee('1,281.36')
        ->assertSee('8,400.00')
        ->assertSee('336.00')
        ->assertSee('8,064.00')
        ->assertPresent('[aria-label="Editar Fecha del depósito de la detracción"]')
        ->assertNoJavaScriptErrors();
});

it('asocia a mano una factura de la bandeja con las GR del período ya marcadas', function (): void {
    actingAs(actorConRol('admin'));
    $viaje = Viaje::factory()->create([
        'numero_gr' => 'EG03-00012257',
        'cliente_ruc' => '20536557858',
        'fecha_traslado' => '2026-08-27',
    ]);
    Factura::factory()->create([
        'numero' => 'E001-14444',
        'cliente_ruc' => '20536557858',
        'cliente_razon_social' => 'HOMECENTERS PERUANOS S.A.',
        'fecha_emision' => '2026-10-06',
        'periodo_desde' => '2026-08-25',
        'periodo_hasta' => '2026-09-14',
    ]);

    visit('/contabilidad')
        ->on()->desktop()
        ->assertSee('Subir facturas')
        ->click('1 factura por asociar')
        ->assertSee('No cita GR: cobra el período del 25/08 al 14/09/2026.')
        ->press('Asociar GR')
        ->assertSee('EG03-00012257')
        ->press('Asociar')
        ->assertSee('Factura E001-14444 asociada a 1 GR.')
        ->assertDontSee('1 factura por asociar')
        ->assertNoJavaScriptErrors();

    expect($viaje->facturas()->pluck('numero')->all())->toBe(['E001-14444']);
});
