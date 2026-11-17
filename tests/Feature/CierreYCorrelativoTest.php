<?php

use App\Models\CierreDeMes;
use App\Models\CuentaBancaria;
use App\Models\Factura;
use App\Models\NumeroDeFacturaExplicado;
use App\Models\Viaje;
use App\Services\ControlDeCorrelativo;
use App\Services\RegistroDeCobros;
use Illuminate\Support\Carbon;

use function Pest\Laravel\actingAs;

/**
 * El cierre de mes, el control del correlativo y el corte de la cobranza
 * desde su inicio (septiembre de 2026).
 */
beforeEach(function (): void {
    Carbon::setTestNow('2026-11-17 10:00:00');
    $this->contador = actorConRol('contador');
});

afterEach(fn () => Carbon::setTestNow());

it('closes a finished month and locks its facturas and cobros', function (): void {
    $cuenta = CuentaBancaria::factory()->create();
    $deOctubre = Factura::factory()->create(['fecha_emision' => '2026-10-10', 'monto' => null, 'total' => 1000, 'detraccion_por_empresa' => true]);
    $viaje = Viaje::factory()->hasAttached($deOctubre, relationship: 'facturas')->create();

    // El mes en curso no se cierra; uno terminado sí, y solo con su permiso.
    actingAs($this->contador)->post(route('cierres.store'), ['mes' => '2026-11'])->assertSessionHasErrors('mes');
    actingAs(actorConRol('gerencia'))->post(route('cierres.store'), ['mes' => '2026-10'])->assertForbidden();
    actingAs($this->contador)->post(route('cierres.store'), ['mes' => '2026-10'])->assertSessionHasNoErrors();

    expect(CierreDeMes::estaCerrado('2026-10-10'))->toBeTrue();

    // Ni corregirla, ni anularla, ni sacarle una GR, ni registrar otra de ese mes.
    actingAs($this->contador)->patch(route('facturas.update', $deOctubre), ['total' => 2000])->assertSessionHasErrors('mes');
    actingAs($this->contador)->post(route('facturas.anular', $deOctubre), ['motivo' => 'Emitida por error'])->assertSessionHasErrors('mes');
    actingAs($this->contador)->delete(route('facturas.desvincular', [$deOctubre, $viaje]))->assertSessionHasErrors('mes');
    actingAs($this->contador)
        ->post(route('facturas.store'), ['numero' => 'E001-99999', 'fecha_emision' => '2026-10-20', 'total' => 1180, 'moneda' => 'PEN', 'viaje_ids' => [Viaje::factory()->create()->id]])
        ->assertSessionHasErrors('mes');
    // Un cobro con fecha de ese mes tampoco entra.
    actingAs($this->contador)
        ->post(route('cobros.store'), ['fecha' => '2026-10-28', 'cuenta_bancaria_id' => $cuenta->id, 'monto' => 1000, 'moneda' => 'PEN', 'aplicaciones' => [['factura_id' => $deOctubre->id, 'monto' => 1000]]])
        ->assertSessionHasErrors('mes');

    expect((float) $deOctubre->fresh()->total)->toBe(1000.0)
        ->and($deOctubre->fresh()->estaAnulada())->toBeFalse()
        ->and($deOctubre->fresh()->viajes()->count())->toBe(1);

    // Cobrarla después sí: ese cobro es de noviembre.
    actingAs($this->contador)
        ->post(route('cobros.store'), ['fecha' => '2026-11-05', 'cuenta_bancaria_id' => $cuenta->id, 'monto' => 1000, 'moneda' => 'PEN', 'aplicaciones' => [['factura_id' => $deOctubre->id, 'monto' => 1000]]])
        ->assertSessionHasNoErrors();
    // Y registrarle la constancia de la detracción, también.
    actingAs($this->contador)
        ->patch(route('facturas.update', $deOctubre), ['fecha_detraccion' => '2026-11-06', 'constancia_detraccion' => '123456'])
        ->assertSessionHasNoErrors();

    expect($deOctubre->fresh()->fecha_pago?->toDateString())->toBe('2026-11-05')
        ->and($deOctubre->fresh()->constancia_detraccion)->toBe('123456');
});

it('reopens a month so it can be corrected again', function (): void {
    $factura = Factura::factory()->create(['fecha_emision' => '2026-10-10', 'monto' => null, 'total' => 1000]);
    CierreDeMes::query()->create(['mes' => '2026-10', 'cerrado_por' => $this->contador->id]);

    actingAs($this->contador)->delete(route('cierres.destroy', '2026-10'))->assertSessionHasNoErrors();
    actingAs($this->contador)->patch(route('facturas.update', $factura), ['total' => 2000])->assertSessionHasNoErrors();

    expect(CierreDeMes::query()->count())->toBe(0)
        ->and((float) $factura->fresh()->total)->toBe(2000.0);
});

it('does not let a cobro of a closed month be anulado', function (): void {
    $factura = Factura::factory()->create(['fecha_emision' => '2026-10-10', 'monto' => null, 'total' => 1000, 'detraccion_por_empresa' => true])->fresh();
    $cobro = app(RegistroDeCobros::class)->registrar(
        ['fecha' => '2026-10-20', 'monto' => 1000.0, 'moneda' => 'PEN'],
        [$factura->id => 1000.0],
        $this->contador,
    );
    CierreDeMes::query()->create(['mes' => '2026-10', 'cerrado_por' => $this->contador->id]);

    actingAs($this->contador)->post(route('cobros.anular', $cobro), ['motivo' => 'Depósito de otro cliente'])->assertSessionHasErrors('mes');

    expect($cobro->fresh()->estaAnulado())->toBeFalse();
});

it('lists the numbers missing from the correlativo and stops warning once explained', function (): void {
    foreach (['E001-14330' => '2026-09-01', 'E001-14331' => '2026-09-02', 'E001-14335' => '2026-09-05', 'E001-14337' => '2026-09-08'] as $numero => $fecha) {
        Factura::factory()->create(['numero' => $numero, 'fecha_emision' => $fecha]);
    }
    // Anterior al inicio de la cobranza: no abre el rango hacia atrás.
    Factura::factory()->create(['numero' => 'E001-14282', 'fecha_emision' => '2026-08-31']);
    // Una anulada ocupa su número: no falta.
    Factura::factory()->create(['numero' => 'E001-14336', 'fecha_emision' => '2026-09-06'])->anular('Emitida por error', $this->contador);

    $faltantes = app(ControlDeCorrelativo::class)->faltantes();

    expect($faltantes)->toHaveCount(1)
        ->and($faltantes[0]['serie'])->toBe('E001')
        ->and($faltantes[0]['faltan'])->toBe(3)
        ->and($faltantes[0]['rangos'])->toBe([['desde' => 14332, 'hasta' => 14334, 'cantidad' => 3, 'despues_del' => '2026-09-02']]);

    actingAs(actorConRol('gerencia'))->post(route('correlativo.store'), ['serie' => 'E001', 'desde' => 14332, 'hasta' => 14333, 'motivo' => 'anulada_sunat'])->assertForbidden();
    actingAs($this->contador)->post(route('correlativo.store'), ['serie' => 'E001', 'desde' => 14332, 'hasta' => 14333, 'motivo' => 'otro'])->assertSessionHasErrors('nota');
    actingAs($this->contador)->post(route('correlativo.store'), ['serie' => 'E001', 'desde' => 14332, 'hasta' => 14333, 'motivo' => 'anulada_sunat'])->assertSessionHasNoErrors();

    $faltantes = app(ControlDeCorrelativo::class)->faltantes();

    expect(NumeroDeFacturaExplicado::query()->count())->toBe(2)
        ->and($faltantes[0]['faltan'])->toBe(1)
        ->and($faltantes[0]['rangos'][0]['desde'])->toBe(14334);
});

it('leaves out of the cobranza the GR from before it started, unless they were invoiced', function (): void {
    Viaje::factory()->create(['numero_gr' => 'EG03-VIEJA', 'fecha_traslado' => '2026-08-10']);
    $facturada = Viaje::factory()->create(['numero_gr' => 'EG03-VIEJA-FACTURADA', 'fecha_traslado' => '2026-08-12']);
    $facturada->facturas()->attach(Factura::factory()->create(['fecha_emision' => '2026-09-03']));
    Viaje::factory()->create(['numero_gr' => 'EG03-NUEVA', 'fecha_traslado' => '2026-09-15']);

    actingAs($this->contador)
        ->get(route('contabilidad.index'))
        ->assertInertia(fn ($page) => $page
            ->has('viajes.data', 2)
            ->where('viajes.data.0.numero_gr', 'EG03-NUEVA')
            ->where('viajes.data.1.numero_gr', 'EG03-VIEJA-FACTURADA')
            ->has('cierres')
            ->has('correlativo')
        );
});
