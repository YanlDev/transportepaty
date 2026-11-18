<?php

use App\Enums\EstadoCobranza;
use App\Models\Cliente;
use App\Models\Cobro;
use App\Models\CuentaBancaria;
use App\Models\Factura;
use App\Models\Viaje;
use App\Services\RegistroDeCobros;
use App\Services\ResumenCobranza;

use function Pest\Laravel\actingAs;

/**
 * El cobro es un registro propio: un depósito que se reparte entre una o
 * varias facturas. La factura calcula sola lo que lleva cobrado y, cuando ya
 * entró todo su neto, toma la fecha, la cuenta y la operación del cobro.
 */
beforeEach(function (): void {
    $this->contador = actorConRol('contador');
    $this->cuenta = CuentaBancaria::factory()->create();
});

/** Una factura por cobrar de `$total` con IGV, sin detracción que descontar. */
function facturaPorCobrar(float $total, array $atributos = []): Factura
{
    return Factura::factory()->create(['monto' => null, 'total' => $total, 'detraccion_por_empresa' => true, ...$atributos])->fresh();
}

/**
 * @param  array<int, float>  $aplicaciones  factura_id => monto
 * @return array<string, mixed>
 */
function deposito(float $monto, array $aplicaciones, CuentaBancaria $cuenta, array $extra = []): array
{
    return [
        'fecha' => '2026-11-10',
        'cuenta_bancaria_id' => $cuenta->id,
        'numero_operacion' => '00123456',
        'monto' => $monto,
        'moneda' => 'PEN',
        'aplicaciones' => collect($aplicaciones)->map(fn (float $monto, int $id): array => ['factura_id' => $id, 'monto' => $monto])->values()->all(),
        ...$extra,
    ];
}

it('pays several facturas with one deposit', function (): void {
    $una = facturaPorCobrar(1000);
    $otra = facturaPorCobrar(2500);

    actingAs($this->contador)
        ->post(route('cobros.store'), deposito(3500, [$una->id => 1000.0, $otra->id => 2500.0], $this->cuenta))
        ->assertSessionHasNoErrors();

    $cobro = Cobro::query()->with('facturas')->sole();

    expect($cobro->aplicado())->toBe(3500.0)
        ->and($cobro->saldoAFavor())->toBe(0.0)
        ->and($una->fresh()->estado())->toBe(EstadoCobranza::Pagado)
        ->and($otra->fresh()->fecha_pago->toDateString())->toBe('2026-11-10')
        ->and($otra->fresh()->cuenta_bancaria_id)->toBe($this->cuenta->id)
        ->and($otra->fresh()->numero_operacion)->toBe('00123456');
});

it('lets a factura be paid in parts and says how much is left', function (): void {
    $factura = facturaPorCobrar(1000);

    actingAs($this->contador)
        ->post(route('cobros.store'), deposito(400, [$factura->id => 400.0], $this->cuenta))
        ->assertSessionHasNoErrors();

    expect($factura->fresh()->estado())->toBe(EstadoCobranza::Parcial)
        ->and($factura->fresh()->fecha_pago)->toBeNull()
        ->and($factura->fresh()->montoCobrado())->toBe(400.0)
        ->and($factura->fresh()->saldo())->toBe(600.0);

    actingAs($this->contador)
        ->post(route('cobros.store'), deposito(600, [$factura->id => 600.0], $this->cuenta, ['fecha' => '2026-11-12', 'numero_operacion' => '00999']))
        ->assertSessionHasNoErrors();

    expect($factura->fresh()->estado())->toBe(EstadoCobranza::Pagado)
        ->and($factura->fresh()->fecha_pago->toDateString())->toBe('2026-11-12')
        ->and($factura->fresh()->saldo())->toBe(0.0);
});

it('never takes more than the factura owes nor more than the deposit', function (): void {
    $factura = facturaPorCobrar(1000);

    actingAs($this->contador)
        ->post(route('cobros.store'), deposito(1500, [$factura->id => 1200.0], $this->cuenta, ['cliente_id' => Cliente::factory()->create()->id]))
        ->assertSessionHasErrors("aplicaciones.{$factura->id}");
    actingAs($this->contador)
        ->post(route('cobros.store'), deposito(500, [$factura->id => 800.0], $this->cuenta))
        ->assertSessionHasErrors('monto');

    expect(Cobro::query()->count())->toBe(0)
        ->and($factura->fresh()->saldo())->toBe(1000.0);
});

it('keeps an advance as balance in favour of the client and applies it later', function (): void {
    $cliente = Cliente::factory()->create();

    // Sin cliente no se sabe de quién es el saldo.
    actingAs($this->contador)
        ->post(route('cobros.store'), deposito(2000, [], $this->cuenta))
        ->assertSessionHasErrors('cliente_id');
    actingAs($this->contador)
        ->post(route('cobros.store'), deposito(2000, [], $this->cuenta, ['cliente_id' => $cliente->id]))
        ->assertSessionHasNoErrors();

    $adelanto = Cobro::query()->with('facturas')->sole();
    $factura = facturaPorCobrar(1500);

    expect($adelanto->saldoAFavor())->toBe(2000.0);

    actingAs($this->contador)
        ->post(route('cobros.aplicar', $adelanto), ['aplicaciones' => [['factura_id' => $factura->id, 'monto' => 1500]]])
        ->assertSessionHasNoErrors();

    expect($adelanto->fresh()->load('facturas')->saldoAFavor())->toBe(500.0)
        ->and($factura->fresh()->estado())->toBe(EstadoCobranza::Pagado);

    // Del saldo que queda no sale más de lo que hay.
    $otra = facturaPorCobrar(900);

    actingAs($this->contador)
        ->post(route('cobros.aplicar', $adelanto), ['aplicaciones' => [['factura_id' => $otra->id, 'monto' => 900]]])
        ->assertSessionHasErrors('monto');
});

it('anula a cobro with a reason and leaves its facturas owing again', function (): void {
    $factura = facturaPorCobrar(1000);
    $cobro = app(RegistroDeCobros::class)->registrar(
        ['fecha' => '2026-11-10', 'cuenta_bancaria_id' => $this->cuenta->id, 'monto' => 1000.0, 'moneda' => 'PEN'],
        [$factura->id => 1000.0],
        $this->contador,
    );
    $soloCobra = actorConRol('visor');
    $soloCobra->givePermissionTo('cobranza.ver', 'cobranza.gestionar');

    actingAs($soloCobra)->post(route('cobros.anular', $cobro), ['motivo' => 'Depósito de otro cliente'])->assertForbidden();
    actingAs($this->contador)->post(route('cobros.anular', $cobro), ['motivo' => ''])->assertSessionHasErrors('motivo');
    actingAs($this->contador)->post(route('cobros.anular', $cobro), ['motivo' => 'Depósito de otro cliente'])->assertSessionHasNoErrors();

    expect(Cobro::query()->count())->toBe(0)
        ->and(Cobro::query()->conAnulados()->sole()->motivo_anulacion)->toBe('Depósito de otro cliente')
        ->and($factura->fresh()->estado())->toBe(EstadoCobranza::Facturado)
        ->and($factura->fresh()->saldo())->toBe(1000.0);
});

it('turns the payment written on the factura row into its own cobro', function (): void {
    $factura = facturaPorCobrar(1180);

    actingAs($this->contador)
        ->patch(route('facturas.update', $factura), ['fecha_pago' => '2026-11-10', 'cuenta_bancaria_id' => $this->cuenta->id])
        ->assertSessionHasNoErrors();
    actingAs($this->contador)
        ->patch(route('facturas.update', $factura), ['numero_operacion' => '777'])
        ->assertSessionHasNoErrors();

    $cobro = Cobro::query()->with('facturas')->sole();

    expect((float) $cobro->monto)->toBe(1180.0)
        ->and($cobro->numero_operacion)->toBe('777')
        ->and($factura->fresh()->numero_operacion)->toBe('777')
        ->and($factura->fresh()->estado())->toBe(EstadoCobranza::Pagado);

    // Quitar la fecha anula ese cobro: la factura vuelve a deber.
    actingAs($this->contador)
        ->patch(route('facturas.update', $factura), ['fecha_pago' => null])
        ->assertSessionHasNoErrors();

    expect(Cobro::query()->count())->toBe(0)
        ->and($factura->fresh()->fecha_pago)->toBeNull()
        ->and($factura->fresh()->saldo())->toBe(1180.0);
});

it('does not let a shared cobro be corrected from one factura row', function (): void {
    $una = facturaPorCobrar(1000);
    $otra = facturaPorCobrar(500);
    app(RegistroDeCobros::class)->registrar(
        ['fecha' => '2026-11-10', 'cuenta_bancaria_id' => $this->cuenta->id, 'monto' => 1500.0, 'moneda' => 'PEN'],
        [$una->id => 1000.0, $otra->id => 500.0],
        $this->contador,
    );

    actingAs($this->contador)
        ->patch(route('facturas.update', $una), ['fecha_pago' => null])
        ->assertSessionHasErrors('fecha_pago');

    expect($una->fresh()->estado())->toBe(EstadoCobranza::Pagado);
});

it('measures what was collected again when the neto of the factura changes', function (): void {
    $factura = facturaPorCobrar(1000);
    app(RegistroDeCobros::class)->registrar(
        ['fecha' => '2026-11-10', 'cuenta_bancaria_id' => $this->cuenta->id, 'monto' => 1000.0, 'moneda' => 'PEN'],
        [$factura->id => 1000.0],
        $this->contador,
    );

    $factura->fresh()->update(['total' => 1300]);

    expect($factura->fresh()->estado())->toBe(EstadoCobranza::Parcial)
        ->and($factura->fresh()->saldo())->toBe(300.0);
});

it('takes who deposits the detraccion from the client', function (): void {
    $chico = Cliente::factory()->create(['ruc' => '20600812913', 'detraccion_por_empresa' => true]);
    $grande = Cliente::factory()->create(['ruc' => '20100136741']);

    $delChico = Factura::factory()->create(['monto' => null, 'total' => 5000, 'cliente_ruc' => $chico->ruc])->fresh();
    $delGrande = Factura::factory()->create(['monto' => null, 'total' => 5000, 'cliente_ruc' => $grande->ruc])->fresh();

    // Al chico se le cobra el total y la detracción la deposita la empresa.
    expect($delChico->detraccion_por_empresa)->toBeTrue()
        ->and((float) $delChico->neto)->toBe(5000.0)
        ->and($delChico->detraccionPorDepositar())->toBeTrue()
        ->and($delGrande->detraccion_por_empresa)->toBeFalse()
        ->and((float) $delGrande->neto)->toBe(4800.0);
});

it('shows the cobros with what each one paid and the balance in favour', function (): void {
    $cliente = Cliente::factory()->create();
    $factura = facturaPorCobrar(1000);
    Viaje::factory()->hasAttached($factura, relationship: 'facturas')->create();
    app(RegistroDeCobros::class)->registrar(
        ['fecha' => '2026-11-10', 'cuenta_bancaria_id' => $this->cuenta->id, 'monto' => 1400.0, 'moneda' => 'PEN', 'cliente_id' => $cliente->id],
        [$factura->id => 1000.0],
        $this->contador,
    );

    actingAs($this->contador)
        ->get(route('cobros.index'))
        ->assertInertia(fn ($page) => $page
            ->component('contabilidad/cobros')
            ->has('cobros.data', 1)
            ->where('cobros.data.0.aplicado', 1000)
            ->where('cobros.data.0.saldo_a_favor', 400)
            ->where('cobros.data.0.facturas.0.numero', $factura->numero)
            ->where('saldoAFavor.0.monto', 400)
        );

    actingAs(actorConRol('visor'))->get(route('cobros.index'))->assertForbidden();
});

it('closes the factura when the neto is in, without waiting for the detraccion of the client', function (): void {
    // El cliente deposita su detracción: con el neto adentro, pagada.
    $delCliente = Factura::factory()->create(['monto' => null, 'total' => 5000])->fresh();
    // La deposita la empresa: pagada también, y la detracción queda por depositar.
    $deLaEmpresa = facturaPorCobrar(5000);

    foreach ([$delCliente, $deLaEmpresa] as $factura) {
        actingAs($this->contador)
            ->patch(route('facturas.update', $factura), ['fecha_pago' => '2026-11-10', 'cuenta_bancaria_id' => $this->cuenta->id])
            ->assertSessionHasNoErrors();
    }

    expect($delCliente->fresh()->estado())->toBe(EstadoCobranza::Pagado)
        ->and($delCliente->fresh()->faltaDetraccion())->toBeFalse()
        ->and($deLaEmpresa->fresh()->estado())->toBe(EstadoCobranza::Pagado)
        ->and($deLaEmpresa->fresh()->detraccionPorDepositar())->toBeTrue();
});

it('lists apart the detracciones the company still has to deposit', function (): void {
    $cobrada = facturaPorCobrar(5000);
    $sinCobrar = facturaPorCobrar(2500);
    Factura::factory()->create(['monto' => null, 'total' => 9000]);
    app(RegistroDeCobros::class)->registrar(
        ['fecha' => now()->subDays(3)->toDateString(), 'cuenta_bancaria_id' => $this->cuenta->id, 'monto' => 5000.0, 'moneda' => 'PEN'],
        [$cobrada->id => 5000.0],
        $this->contador,
    );

    actingAs($this->contador)
        ->get(route('detracciones.index'))
        ->assertInertia(fn ($page) => $page
            ->component('contabilidad/detracciones')
            // Solo las que deposita la empresa; primero la que ya se cobró.
            ->has('pendientes', 2)
            ->where('pendientes.0.numero', $cobrada->numero)
            ->where('pendientes.0.cobrada', true)
            ->where('pendientes.0.dias_desde_el_cobro', 3)
            ->where('pendientes.1.numero', $sinCobrar->numero)
            ->where('totales.por_depositar', 200)
            ->where('totales.sin_cobrar', 100)
        );

    // Con el depósito registrado sale de la lista.
    actingAs($this->contador)
        ->patch(route('facturas.update', $cobrada), ['fecha_detraccion' => now()->toDateString(), 'constancia_detraccion' => '123456'])
        ->assertSessionHasNoErrors();
    actingAs($this->contador)
        ->get(route('detracciones.index'))
        ->assertInertia(fn ($page) => $page->has('pendientes', 1)->where('totales.por_depositar', 0));

    actingAs(actorConRol('visor'))->get(route('detracciones.index'))->assertForbidden();
});

it('gives the management the cobranza at a glance', function (): void {
    $this->travelTo('2026-11-17 10:00');
    $pagada = facturaPorCobrar(5000, ['fecha_emision' => '2026-10-05', 'cliente_ruc' => '20600812913', 'cliente_razon_social' => 'IMPORTACIONES MELMA S.A.C.']);
    facturaPorCobrar(3000, ['fecha_emision' => '2026-10-01', 'cliente_ruc' => '20100136741', 'cliente_razon_social' => 'MINSUR S.A.']);
    // Anterior al inicio de la cobranza: no cuenta.
    facturaPorCobrar(9999, ['fecha_emision' => '2026-08-10']);
    app(RegistroDeCobros::class)->registrar(
        ['fecha' => '2026-11-03', 'cuenta_bancaria_id' => $this->cuenta->id, 'monto' => 5000.0, 'moneda' => 'PEN'],
        [$pagada->id => 5000.0],
        $this->contador,
    );
    Viaje::factory()->count(2)->create(['fecha_traslado' => '2026-11-10', 'cliente' => 'CRISAR LOGISTICA S.A.C.']);
    Viaje::factory()->create(['fecha_traslado' => '2026-08-01']);

    actingAs(actorConRol('gerencia'))
        ->get(route('contabilidad.reporte'))
        ->assertInertia(fn ($page) => $page
            ->component('contabilidad/reporte')
            ->where('reporte.por_facturar.guias', 2)
            ->where('reporte.por_facturar.clientes.0.cliente', 'CRISAR LOGISTICA S.A.C.')
            ->where('reporte.por_cobrar.facturas', 1)
            ->where('reporte.por_cobrar.montos.0.monto', 3000)
            ->where('reporte.por_cobrar.facturas_vencidas', 1)
            ->where('reporte.por_cobrar.clientes.0.detalle.0.saldo', 3000)
            ->where('reporte.cobrado.montos.0.monto', 5000)
            // La que deposita la empresa: siempre a la vista.
            ->where('reporte.detraccion.por_depositar', 200)
            ->where('reporte.detraccion.facturas_por_depositar', 1)
            ->has('reporte.por_mes', 3)
        );

    actingAs(actorConRol('visor'))->get(route('contabilidad.reporte'))->assertForbidden();
});

it('reads a month of the management summary by the date of the trips, like the table', function (): void {
    $this->travelTo('2026-11-17 10:00');
    $deSetiembre = Viaje::factory()->count(2)->create(['fecha_traslado' => '2026-09-20']);
    $deOctubre = Viaje::factory()->create(['fecha_traslado' => '2026-10-02']);
    // Emitida en octubre por viajes de los dos meses: 2/3 es de setiembre.
    $repartida = facturaPorCobrar(3000, ['fecha_emision' => '2026-10-05', 'cliente_ruc' => '20100136741', 'cliente_razon_social' => 'MINSUR S.A.']);
    $repartida->viajes()->attach([...$deSetiembre->modelKeys(), $deOctubre->id]);
    // Emitida en setiembre por un viaje de agosto: no es de setiembre.
    $deAgosto = facturaPorCobrar(9000, ['fecha_emision' => '2026-09-22']);
    $deAgosto->viajes()->attach(Viaje::factory()->create(['fecha_traslado' => '2026-08-30']));
    // Un depósito de noviembre que paga la mitad de la repartida.
    app(RegistroDeCobros::class)->registrar(
        ['fecha' => '2026-11-03', 'cuenta_bancaria_id' => $this->cuenta->id, 'monto' => 1500.0, 'moneda' => 'PEN'],
        [$repartida->id => 1500.0],
        $this->contador,
    );

    actingAs($this->contador)
        ->get(route('contabilidad.reporte', ['mes' => '2026-09']))
        ->assertInertia(fn ($page) => $page
            ->where('reporte.facturado.facturas', 1)
            ->where('reporte.facturado.montos.0.monto', 2000)
            ->where('reporte.por_cobrar.facturas', 1)
            ->where('reporte.por_cobrar.montos.0.monto', 1000)
            ->where('reporte.por_cobrar.clientes.0.detalle.0.numero', $repartida->numero)
            ->where('reporte.por_cobrar.clientes.0.detalle.0.saldo', 1000)
            ->where('reporte.por_cobrar.clientes.0.detalle.0.repartida', true)
            // Entró en noviembre, pero es plata de los viajes de setiembre.
            ->where('reporte.cobrado.cobros', 1)
            ->where('reporte.cobrado.montos.0.monto', 1000)
            ->where('reporte.avance.facturado', 2000)
            ->where('reporte.avance.cobrado', 1000)
        );

    // Lo mismo que suma la tabla de Contabilidad con el mes puesto.
    $deLaTabla = app(ResumenCobranza::class)->totales(['buscar' => null, 'cliente' => null, 'estado' => null, 'mes' => '2026-09', 'desde' => null, 'hasta' => null]);
    expect($deLaTabla['montos'][0]['por_cobrar'])->toBe(1000.0);

    actingAs($this->contador)
        ->get(route('contabilidad.reporte', ['mes' => '2026-10']))
        ->assertInertia(fn ($page) => $page
            ->where('reporte.facturado.montos.0.monto', 1000)
            ->where('reporte.por_cobrar.montos.0.monto', 500)
            ->where('reporte.cobrado.montos.0.monto', 500)
        );
});
