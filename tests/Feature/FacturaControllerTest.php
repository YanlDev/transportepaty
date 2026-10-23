<?php

use App\Enums\TipoCarga;
use App\Models\CuentaBancaria;
use App\Models\Factura;
use App\Models\Viaje;
use App\Services\RelojOperativo;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    foreach (['admin', 'visor', 'conductor', 'contador'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

/**
 * Emitir pide solo el número: el resto de la fila se completa después, celda
 * por celda, que es como se llena la cobranza en pantalla.
 *
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function datosFactura(array $extra = []): array
{
    return ['numero' => 'F001-00123', ...$extra];
}

it('keeps the visor and the conductor from facturando', function (): void {
    $viaje = Viaje::factory()->create();

    foreach (['visor', 'conductor'] as $rol) {
        actingAs(actorConRol($rol))
            ->post(route('facturas.store'), datosFactura(['viaje_ids' => [$viaje->id]]))
            ->assertForbidden();
    }

    expect(Factura::query()->count())->toBe(0);
});

/**
 * El caso de la gaseosa: dos GR que salieron en el mismo camión se cobran una
 * sola vez. Es la razón de que el alta reciba una lista de viajes.
 */
it('covers several viajes with a single factura', function (): void {
    $viajes = Viaje::factory()->count(2)->create();

    actingAs(actorConRol('contador'))
        ->post(route('facturas.store'), datosFactura([
            'viaje_ids' => $viajes->pluck('id')->all(),
        ]))
        ->assertSessionHasNoErrors();

    $factura = Factura::query()->sole();

    expect($factura->numero)->toBe('F001-00123')
        ->and($factura->viajes()->count())->toBe(2)
        ->and($factura->estado()->value)->toBe('facturado');
});

/**
 * El número se conoce al emitir; el monto puede llegar después. Registrar la
 * factura sin cifra es preferible a inventar una para poder guardarla.
 */
it('registers a factura with no monto yet', function (): void {
    $viaje = Viaje::factory()->create();

    actingAs(actorConRol('contador'))
        ->post(route('facturas.store'), datosFactura(['viaje_ids' => [$viaje->id]]))
        ->assertSessionHasNoErrors();

    $factura = Factura::query()->sole();

    expect($factura->monto)->toBeNull()
        // Sin fecha explícita se asume hoy: se registra el día que se emite.
        ->and($factura->fecha_emision->toDateString())->toBe(now()->toDateString())
        ->and($factura->moneda->value)->toBe('PEN');
});

it('uppercases the numero de factura', function (): void {
    $viaje = Viaje::factory()->create();

    actingAs(actorConRol('contador'))
        ->post(route('facturas.store'), datosFactura([
            'numero' => ' f001-00777 ',
            'viaje_ids' => [$viaje->id],
        ]))
        ->assertSessionHasNoErrors();

    expect(Factura::query()->sole()->numero)->toBe('F001-00777');
});

/**
 * El flete y la estadía de una misma GR se facturan por separado: un viaje ya
 * facturado tiene que poder recibir otra factura sin perder la primera.
 */
it('adds a second factura to a viaje that already has one', function (): void {
    $primera = Factura::factory()->create();
    $viaje = Viaje::factory()->hasAttached($primera, relationship: 'facturas')->create();

    actingAs(actorConRol('contador'))
        ->post(route('facturas.store'), datosFactura([
            'numero' => 'F001-00200',
            'viaje_ids' => [$viaje->id],
        ]))
        ->assertSessionHasNoErrors();

    expect($viaje->facturas()->pluck('numero')->all())
        ->toEqualCanonicalizing([$primera->numero, 'F001-00200']);
});

it('rejects the same viaje twice in one factura', function (): void {
    $viaje = Viaje::factory()->create();

    actingAs(actorConRol('contador'))
        ->post(route('facturas.store'), datosFactura(['viaje_ids' => [$viaje->id, $viaje->id]]))
        ->assertSessionHasErrors('viaje_ids.0');

    expect(Factura::query()->count())->toBe(0);
});

it('rejects a duplicated numero de factura', function (): void {
    Factura::factory()->create(['numero' => 'F001-00123']);
    $viaje = Viaje::factory()->create();

    actingAs(actorConRol('contador'))
        ->post(route('facturas.store'), datosFactura(['viaje_ids' => [$viaje->id]]))
        ->assertSessionHasErrors('numero');
});

it('requires at least one viaje', function (): void {
    actingAs(actorConRol('contador'))
        ->post(route('facturas.store'), datosFactura(['viaje_ids' => []]))
        ->assertSessionHasErrors('viaje_ids');
});

it('records the cobro with its cuenta', function (): void {
    $cuenta = CuentaBancaria::factory()->create();
    $factura = Factura::factory()->create(['fecha_emision' => '2026-09-01', 'monto' => 3500]);
    Viaje::factory()->hasAttached($factura, relationship: 'facturas')->create();

    actingAs(actorConRol('contador'))
        ->patch(route('facturas.update', $factura), ['fecha_pago' => '2026-09-20'])
        ->assertSessionHasNoErrors();

    actingAs(actorConRol('contador'))
        ->patch(route('facturas.update', $factura), ['cuenta_bancaria_id' => $cuenta->id])
        ->assertSessionHasNoErrors();

    $factura->refresh();

    expect($factura->fecha_pago?->toDateString())->toBe('2026-09-20')
        ->and($factura->cuenta_bancaria_id)->toBe($cuenta->id)
        // Entró el neto, pero la detracción todavía no.
        ->and($factura->estado()->value)->toBe('falta_detraccion');

    actingAs(actorConRol('contador'))
        ->patch(route('facturas.update', $factura), ['fecha_detraccion' => '2026-09-22', 'constancia_detraccion' => '123456789'])
        ->assertSessionHasNoErrors();

    $factura->refresh();

    expect($factura->fecha_detraccion?->toDateString())->toBe('2026-09-22')
        ->and($factura->constancia_detraccion)->toBe('123456789')
        ->and($factura->estado()->value)->toBe('pagado');
});

/**
 * Cada celda manda solo su campo. Si el update pisara el resto con valores por
 * defecto, guardar el monto borraría la fecha de pago cargada hace un momento.
 */
it('saves one cell without touching the rest of the factura', function (): void {
    $cuenta = CuentaBancaria::factory()->create();
    $factura = Factura::factory()->create([
        'fecha_emision' => '2026-09-01',
        'fecha_pago' => '2026-09-10',
        'cuenta_bancaria_id' => $cuenta->id,
        'observacion' => 'Detracción pendiente',
    ]);

    actingAs(actorConRol('contador'))
        ->patch(route('facturas.update', $factura), ['monto' => 6000])
        ->assertSessionHasNoErrors();

    $factura->refresh();

    expect((float) $factura->monto)->toBe(6000.0)
        ->and($factura->fecha_pago?->toDateString())->toBe('2026-09-10')
        ->and($factura->cuenta_bancaria_id)->toBe($cuenta->id)
        ->and($factura->observacion)->toBe('Detracción pendiente');
});

/**
 * Borrar la fecha de pago suelta la cuenta: dejarla apuntando a un banco sería
 * decir que se cobró por ahí algo que ya no está cobrado.
 */
it('drops the cuenta when the fecha de pago is cleared', function (): void {
    $cuenta = CuentaBancaria::factory()->create();
    $factura = Factura::factory()->create([
        'fecha_pago' => '2026-09-10',
        'cuenta_bancaria_id' => $cuenta->id,
    ]);

    actingAs(actorConRol('contador'))
        ->patch(route('facturas.update', $factura), ['fecha_pago' => null])
        ->assertSessionHasNoErrors();

    $factura->refresh();

    expect($factura->fecha_pago)->toBeNull()
        ->and($factura->cuenta_bancaria_id)->toBeNull()
        ->and($factura->estado()->value)->toBe('facturado');
});

/**
 * Hay clientes que pagan antes de que salga la factura: depositan el 20 y la
 * facturadora emite el 22. El cobro se registra igual, neto o detracción.
 */
it('records a cobro that arrived before the factura was emitted', function (string $cobro): void {
    $factura = Factura::factory()->create(['fecha_emision' => '2026-09-22', 'monto' => 3500]);
    Viaje::factory()->hasAttached($factura, relationship: 'facturas')->create();

    actingAs(actorConRol('contador'))
        ->patch(route('facturas.update', $factura), [$cobro => '2026-09-20'])
        ->assertSessionHasNoErrors();

    expect($factura->fresh()->{$cobro}?->toDateString())->toBe('2026-09-20');
})->with(['fecha_pago', 'fecha_detraccion']);

it('accepts an emision and an earlier cobro in the same request', function (): void {
    $factura = Factura::factory()->create(['fecha_emision' => '2026-09-01']);

    actingAs(actorConRol('contador'))
        ->patch(route('facturas.update', $factura), [
            'fecha_emision' => '2026-09-22',
            'fecha_pago' => '2026-09-20',
        ])
        ->assertSessionHasNoErrors();

    expect($factura->fresh()->fecha_pago?->toDateString())->toBe('2026-09-20');
});

it('accepts both dates when the cobro follows the new emision', function (): void {
    $factura = Factura::factory()->create(['fecha_emision' => '2026-09-01']);

    actingAs(actorConRol('contador'))
        ->patch(route('facturas.update', $factura), [
            'fecha_emision' => '2026-08-01',
            'fecha_pago' => '2026-08-15',
        ])
        ->assertSessionHasNoErrors();

    expect($factura->fresh()->fecha_pago?->toDateString())->toBe('2026-08-15');
});

/**
 * Sacar un viaje de la factura tiene que devolverlo a «sin facturar»: si
 * siguiera enlazado, se daría por cobrado por una factura que ya no lo cubre.
 */
it('frees a single viaje without touching the rest of the factura', function (): void {
    $factura = Factura::factory()->create();
    $viajes = Viaje::factory()->count(2)->hasAttached($factura, relationship: 'facturas')->create();

    actingAs(actorConRol('contador'))
        ->delete(route('facturas.desvincular', [$factura, $viajes->first()]))
        ->assertSessionHasNoErrors();

    expect($viajes->first()->facturas()->exists())->toBeFalse()
        ->and($viajes->last()->facturas()->pluck('facturas.id')->all())->toBe([$factura->id])
        ->and(Factura::query()->count())->toBe(1);
});

/**
 * Con dos facturas sobre la misma GR, sacarla de una no la saca de la otra:
 * el flete puede estar bien facturado aunque la estadía no.
 */
it('frees a viaje from one factura and keeps the other', function (): void {
    $flete = Factura::factory()->create();
    $estadia = Factura::factory()->create();
    $viaje = Viaje::factory()
        ->hasAttached($flete, relationship: 'facturas')
        ->hasAttached($estadia, relationship: 'facturas')
        ->create();

    actingAs(actorConRol('contador'))
        ->delete(route('facturas.desvincular', [$estadia, $viaje]))
        ->assertSessionHasNoErrors();

    expect($viaje->facturas()->pluck('facturas.id')->all())->toBe([$flete->id])
        // Era su único viaje: no se anula, queda por asociar.
        ->and(Factura::query()->whereKey($estadia->id)->whereDoesntHave('viajes')->exists())->toBeTrue();
});

it('refuses to free a viaje from a factura that does not cover it', function (): void {
    $factura = Factura::factory()->create();
    Viaje::factory()->hasAttached($factura, relationship: 'facturas')->create();
    $ajeno = Viaje::factory()->create();

    actingAs(actorConRol('contador'))
        ->delete(route('facturas.desvincular', [$factura, $ajeno]))
        ->assertNotFound();
});

/**
 * Una factura sin ningún viaje ya no cobra nada y no habría forma de llegar a
 * ella desde la tabla, que se recorre por viaje.
 */
/**
 * Sin GR, la factura no se anula sola: pasa a la bandeja «Facturas por
 * asociar», desde donde se le asignan las correctas o se anula a propósito.
 */
it('sends the factura to the bandeja when its last viaje is freed', function (): void {
    $factura = Factura::factory()->create();
    $viaje = Viaje::factory()->hasAttached($factura, relationship: 'facturas')->create();

    actingAs(actorConRol('contador'))
        ->delete(route('facturas.desvincular', [$factura, $viaje]))
        ->assertSessionHasNoErrors();

    expect(Factura::query()->whereKey($factura->id)->exists())->toBeTrue()
        ->and($viaje->facturas()->exists())->toBeFalse();

    actingAs(actorConRol('contador'))
        ->get(route('contabilidad.index'))
        ->assertInertia(fn ($page) => $page
            ->has('facturasPorAsociar', 1)
            ->where('facturasPorAsociar.0.motivo', 'Se quedó sin GR.')
        );
});

it('frees every viaje when the factura is anulada', function (): void {
    $factura = Factura::factory()->create();
    Viaje::factory()->count(2)->hasAttached($factura, relationship: 'facturas')->create();

    actingAs(actorConRol('contador'))
        ->delete(route('facturas.destroy', $factura))
        ->assertSessionHasNoErrors();

    expect(Factura::query()->count())->toBe(0)
        // Los viajes no se van con la factura: anular una emitida por error es
        // justamente para poder volver a facturarlos.
        ->and(Viaje::query()->count())->toBe(2)
        ->and(Viaje::query()->whereHas('facturas')->count())->toBe(0);
});

/**
 * Minsur pacta el flete por viaje: en una factura de doce GR se escribe el
 * precio de una y el total se calcula. Lo guardado es el total, que es lo que
 * suma en la cobranza.
 */
it('turns a precio por GR into the factura total', function (): void {
    $factura = Factura::factory()->create(['monto' => null]);
    Viaje::factory()->count(12)->hasAttached($factura, relationship: 'facturas')->create();

    actingAs(actorConRol('contador'))
        ->patch(route('facturas.update', $factura), ['monto_por_viaje' => 7347.21])
        ->assertSessionHasNoErrors();

    expect((float) $factura->refresh()->monto)->toBe(88166.52);
});

it('rejects a total and a precio por GR in the same request', function (): void {
    $factura = Factura::factory()->create();

    actingAs(actorConRol('contador'))
        ->patch(route('facturas.update', $factura), ['monto' => 100, 'monto_por_viaje' => 50])
        ->assertSessionHasErrors('monto_por_viaje');
});

it('refuses to facturar a GR marked as no facturable', function (): void {
    $viaje = Viaje::factory()->create(['numero_gr' => 'EG03-CAJITA', 'no_facturable_at' => now()]);

    actingAs(actorConRol('contador'))
        ->post(route('facturas.store'), datosFactura(['viaje_ids' => [$viaje->id]]))
        ->assertSessionHasErrors('viaje_ids');

    expect(Factura::query()->count())->toBe(0);
});

/**
 * El flete pactado con IGV adentro (Crisar, S/ 8 400): se escribe el total y
 * el valor sale dividiendo, sin perder un céntimo del total impreso.
 */
it('derives the valor from a total pactado con IGV', function (): void {
    $factura = Factura::factory()->create(['monto' => null]);
    Viaje::factory()->hasAttached($factura, relationship: 'facturas')->create();

    actingAs(actorConRol('contador'))
        ->patch(route('facturas.update', $factura), ['total' => 8400])
        ->assertSessionHasNoErrors();

    $factura->refresh();

    expect([$factura->monto, $factura->igv, $factura->total, $factura->detraccion, $factura->neto])
        ->toBe(['7118.64', '1281.36', '8400.00', '336.00', '8064.00']);
});

it('recalculates the whole desglose when the valor changes', function (): void {
    $factura = Factura::factory()->create(['monto' => 1000]);
    Viaje::factory()->hasAttached($factura, relationship: 'facturas')->create();

    actingAs(actorConRol('contador'))
        ->patch(route('facturas.update', $factura), ['monto' => 3500])
        ->assertSessionHasNoErrors();

    $factura->refresh();

    expect([$factura->igv, $factura->total, $factura->detraccion, $factura->neto])
        ->toBe(['630.00', '4130.00', '165.00', '3965.00']);
});

it('clears the whole flete when the total is erased', function (): void {
    $factura = Factura::factory()->create(['monto' => 3500]);
    Viaje::factory()->hasAttached($factura, relationship: 'facturas')->create();

    actingAs(actorConRol('contador'))
        ->patch(route('facturas.update', $factura), ['total' => null])
        ->assertSessionHasNoErrors();

    $factura->refresh();

    expect([$factura->monto, $factura->total, $factura->detraccion, $factura->neto])
        ->toBe([null, null, null, null]);
});

it('rejects a total and a valor in the same request', function (): void {
    $factura = Factura::factory()->create();
    Viaje::factory()->hasAttached($factura, relationship: 'facturas')->create();

    actingAs(actorConRol('contador'))
        ->patch(route('facturas.update', $factura), ['total' => 8400, 'monto' => 7118.64])
        ->assertSessionHasErrors('total');
});

/**
 * Un flete chico no lleva detracción: con el neto adentro ya está pagado.
 */
it('counts a factura under the umbral as pagada with just the neto', function (): void {
    $factura = Factura::factory()->create(['monto' => 300, 'fecha_emision' => '2026-09-01']);
    Viaje::factory()->hasAttached($factura, relationship: 'facturas')->create();

    actingAs(actorConRol('contador'))
        ->patch(route('facturas.update', $factura), ['fecha_pago' => '2026-09-10'])
        ->assertSessionHasNoErrors();

    $factura->refresh();

    expect($factura->detraccion)->toBe('0.00')
        ->and($factura->estado()->value)->toBe('pagado');
});

/**
 * La factura emitida tarde, con el cobro ya cargado: corregir la emisión a la
 * fecha real no puede chocar con el pago que llegó antes.
 */
it('lets the emision move past a cobro already recorded', function (string $cobro): void {
    $factura = Factura::factory()->create(['fecha_emision' => '2026-09-01', $cobro => '2026-09-20']);
    Viaje::factory()->hasAttached($factura, relationship: 'facturas')->create();

    actingAs(actorConRol('contador'))
        ->patch(route('facturas.update', $factura), ['fecha_emision' => '2026-09-22'])
        ->assertSessionHasNoErrors();

    expect($factura->fresh()->fecha_emision->toDateString())->toBe('2026-09-22');
})->with(['fecha_pago', 'fecha_detraccion']);

it('refuses to facturar an anulada GR', function (): void {
    $viaje = Viaje::factory()->create(['anulada_at' => now()]);

    actingAs(actorConRol('contador'))
        ->post(route('facturas.store'), datosFactura(['viaje_ids' => [$viaje->id]]))
        ->assertSessionHasErrors('viaje_ids.0');

    expect(Factura::query()->count())->toBe(0);
});

/**
 * Vence a los 30 días de emitida: antes de eso no está vencida, y desde el
 * día siguiente cuenta los días de atraso.
 */
it('counts the days past due from the vencimiento, not from the emision', function (): void {
    $hoy = RelojOperativo::fechaDeHoy();
    $alDia = Factura::factory()->create(['fecha_emision' => $hoy->subDays(20)->toDateString()]);
    $vencida = Factura::factory()->create(['fecha_emision' => $hoy->subDays(45)->toDateString()]);

    expect($alDia->fechaVencimiento()->toDateString())->toBe($hoy->addDays(10)->toDateString())
        ->and($alDia->diasVencida())->toBe(-10)
        ->and($vencida->diasVencida())->toBe(15)
        ->and(Factura::factory()->pagada()->create()->diasVencida())->toBeNull();
});

it('desglosa a factura created with just its total', function (): void {
    $factura = Factura::factory()->create(['monto' => null, 'total' => 8400]);

    expect([$factura->monto, $factura->igv, $factura->total, $factura->neto])
        ->toBe(['7118.64', '1281.36', '8400.00', '8064.00']);
});

it('does not round the detracción of a Minsur factura', function (): void {
    $viaje = Viaje::factory()->create(['cliente_ruc' => TipoCarga::RUC_MINSUR]);

    actingAs(actorConRol('contador'))
        ->post(route('facturas.store'), datosFactura(['viaje_ids' => [$viaje->id]]))
        ->assertSessionHasNoErrors();

    $factura = Factura::query()->sole();

    actingAs(actorConRol('contador'))
        ->patch(route('facturas.update', $factura), ['monto' => 95513.73])
        ->assertSessionHasNoErrors();

    expect($factura->fresh()->detraccion_redondeada)->toBeFalse()
        ->and($factura->fresh()->detraccion)->toBe('4508.25');
});

it('switches the detracción between rounded and with céntimos', function (): void {
    $factura = Factura::factory()->create(['monto' => 95513.73]);
    Viaje::factory()->hasAttached($factura, relationship: 'facturas')->create();

    expect($factura->detraccion)->toBe('4508.00');

    actingAs(actorConRol('contador'))
        ->patch(route('facturas.update', $factura), ['detraccion_redondeada' => false])
        ->assertSessionHasNoErrors();

    expect($factura->fresh()->detraccion)->toBe('4508.25')
        ->and($factura->fresh()->neto)->toBe('108197.95');
});

/**
 * A veces el 4% no coincide con lo que depositó el cliente: la detracción se
 * escribe a mano y el neto sale de ella.
 */
it('takes a detracción written by hand and derives the neto from it', function (): void {
    $factura = Factura::factory()->create(['monto' => 10000]);
    Viaje::factory()->hasAttached($factura, relationship: 'facturas')->create();

    expect([$factura->total, $factura->detraccion])->toBe(['11800.00', '472.00']);

    actingAs(actorConRol('contador'))
        ->patch(route('facturas.update', $factura), ['detraccion' => 470.5])
        ->assertSessionHasNoErrors();

    $factura->refresh();

    expect($factura->detraccion_manual)->toBeTrue()
        ->and([$factura->monto, $factura->total, $factura->detraccion, $factura->neto])
        ->toBe(['10000.00', '11800.00', '470.50', '11329.50']);
});

it('keeps the detracción by hand while other cells are saved', function (): void {
    $factura = Factura::factory()->create(['monto' => 10000]);
    Viaje::factory()->hasAttached($factura, relationship: 'facturas')->create();

    actingAs(actorConRol('contador'))
        ->patch(route('facturas.update', $factura), ['detraccion' => 470.5]);

    actingAs(actorConRol('contador'))
        ->patch(route('facturas.update', $factura), ['fecha_pago' => '2026-09-20', 'observacion' => 'ok'])
        ->assertSessionHasNoErrors();

    expect([$factura->fresh()->detraccion, $factura->fresh()->neto])->toBe(['470.50', '11329.50']);
});

/**
 * Corregir el flete deja la detracción a mano sin sentido: vuelve el cálculo.
 */
it('recalculates the detracción by hand when the valor changes', function (): void {
    $factura = Factura::factory()->create(['monto' => 10000]);
    Viaje::factory()->hasAttached($factura, relationship: 'facturas')->create();

    actingAs(actorConRol('contador'))
        ->patch(route('facturas.update', $factura), ['detraccion' => 470.5]);

    actingAs(actorConRol('contador'))
        ->patch(route('facturas.update', $factura), ['monto' => 20000])
        ->assertSessionHasNoErrors();

    $factura->refresh();

    expect($factura->detraccion_manual)->toBeFalse()
        ->and([$factura->detraccion, $factura->neto])->toBe(['944.00', '22656.00']);
});

it('goes back to the calculated detracción when it is cleared', function (): void {
    $factura = Factura::factory()->create(['monto' => 10000]);
    Viaje::factory()->hasAttached($factura, relationship: 'facturas')->create();

    actingAs(actorConRol('contador'))
        ->patch(route('facturas.update', $factura), ['detraccion' => 470.5]);

    actingAs(actorConRol('contador'))
        ->patch(route('facturas.update', $factura), ['detraccion' => null])
        ->assertSessionHasNoErrors();

    $factura->refresh();

    expect($factura->detraccion_manual)->toBeFalse()
        ->and([$factura->detraccion, $factura->neto])->toBe(['472.00', '11328.00']);
});

it('rejects a detracción by hand above the total', function (): void {
    $factura = Factura::factory()->create(['monto' => 10000]);
    Viaje::factory()->hasAttached($factura, relationship: 'facturas')->create();

    actingAs(actorConRol('contador'))
        ->patch(route('facturas.update', $factura), ['detraccion' => 12000])
        ->assertSessionHasErrors('detraccion');

    expect($factura->fresh()->detraccion)->toBe('472.00');
});

it('rejects a detracción by hand on a factura without flete', function (): void {
    $factura = Factura::factory()->create(['monto' => null]);
    Viaje::factory()->hasAttached($factura, relationship: 'facturas')->create();

    actingAs(actorConRol('contador'))
        ->patch(route('facturas.update', $factura), ['detraccion' => 100])
        ->assertSessionHasErrors('detraccion');
});

it('rejects a detracción by hand together with the valor', function (): void {
    $factura = Factura::factory()->create(['monto' => 10000]);
    Viaje::factory()->hasAttached($factura, relationship: 'facturas')->create();

    actingAs(actorConRol('contador'))
        ->patch(route('facturas.update', $factura), ['detraccion' => 470.5, 'monto' => 20000])
        ->assertSessionHasErrors('detraccion');
});

/**
 * En una factura subida en PDF la detracción también se corrige a mano: el
 * resto de las cifras siguen siendo las impresas, y quitarla devuelve la del
 * PDF, no un cálculo.
 */
it('corrects the detracción of a factura subida en PDF and goes back to the printed one', function (): void {
    $factura = Factura::factory()->create();
    $factura->guardarCifrasImpresas(['monto' => 10000, 'igv' => 1800, 'total' => 11800, 'detraccion' => 471, 'neto' => 11329]);
    $factura->save();
    Viaje::factory()->hasAttached($factura, relationship: 'facturas')->create();

    actingAs(actorConRol('contador'))
        ->patch(route('facturas.update', $factura), ['detraccion' => 470.5])
        ->assertSessionHasNoErrors();

    $factura->refresh();

    expect($factura->desde_pdf)->toBeTrue()
        ->and($factura->detraccion_manual)->toBeTrue()
        ->and([$factura->total, $factura->detraccion, $factura->neto, $factura->detraccion_impresa])
        ->toBe(['11800.00', '470.50', '11329.50', '471.00']);

    actingAs(actorConRol('contador'))
        ->patch(route('facturas.update', $factura), ['detraccion' => null])
        ->assertSessionHasNoErrors();

    $factura->refresh();

    expect($factura->desde_pdf)->toBeTrue()
        ->and($factura->detraccion_manual)->toBeFalse()
        ->and([$factura->detraccion, $factura->neto])->toBe(['471.00', '11329.00']);
});

/**
 * «Facturado a» nace con el cliente de la GR: es a quien se factura casi
 * siempre, y la factura cargada a mano no trae PDF de donde leerlo.
 */
it('bills a new factura to the cliente of its GR', function (): void {
    $viaje = Viaje::factory()->create(['cliente' => 'CRISAR LOGISTICA S.A.C.', 'cliente_ruc' => '20601234567']);

    actingAs(actorConRol('contador'))
        ->post(route('facturas.store'), datosFactura(['viaje_ids' => [$viaje->id]]))
        ->assertSessionHasNoErrors();

    $factura = Factura::query()->sole();

    expect($factura->cliente_razon_social)->toBe('CRISAR LOGISTICA S.A.C.')
        ->and($factura->cliente_ruc)->toBe('20601234567');
});

it('corrects the cliente facturado from its cell', function (): void {
    $factura = Factura::factory()->create(['cliente_razon_social' => 'CRISAR LOGISTICA S.A.C.']);

    actingAs(actorConRol('contador'))
        ->patch(route('facturas.update', $factura), ['cliente_razon_social' => 'AJEPER S.A.'])
        ->assertSessionHasNoErrors();

    actingAs(actorConRol('contador'))
        ->patch(route('facturas.update', $factura), ['cliente_ruc' => '20331061655'])
        ->assertSessionHasNoErrors();

    expect($factura->fresh()->cliente_razon_social)->toBe('AJEPER S.A.')
        ->and($factura->fresh()->cliente_ruc)->toBe('20331061655');
});

it('rejects a RUC facturado that is not eleven digits', function (): void {
    $factura = Factura::factory()->create();

    actingAs(actorConRol('contador'))
        ->patch(route('facturas.update', $factura), ['cliente_ruc' => '2033106'])
        ->assertSessionHasErrors('cliente_ruc');
});

/**
 * Las facturas cargadas a mano antes de la columna toman el cliente de su GR;
 * las que vinieron en PDF conservan el impreso.
 */
it('fills the cliente facturado of the old facturas from their GR', function (): void {
    $aMano = Factura::factory()->create(['cliente_razon_social' => null, 'cliente_ruc' => null]);
    Viaje::factory()->hasAttached($aMano, relationship: 'facturas')
        ->create(['cliente' => 'MOLINORTE TRADING S.A.C.', 'cliente_ruc' => '20512345678']);

    $delPdf = Factura::factory()->create(['cliente_razon_social' => 'MINSUR S. A.', 'cliente_ruc' => '20100136741']);
    Viaje::factory()->hasAttached($delPdf, relationship: 'facturas')->create(['cliente' => 'OTRO']);

    (require database_path('migrations/2026_10_13_140102_llena_facturado_a_de_las_facturas_cargadas_a_mano.php'))->up();

    expect($aMano->fresh()->cliente_razon_social)->toBe('MOLINORTE TRADING S.A.C.')
        ->and($aMano->fresh()->cliente_ruc)->toBe('20512345678')
        ->and($delPdf->fresh()->cliente_razon_social)->toBe('MINSUR S. A.');
});
