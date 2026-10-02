<?php

use App\Models\CuentaBancaria;
use App\Models\Factura;
use App\Models\Viaje;
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
    $factura = Factura::factory()->create(['fecha_emision' => '2026-09-01']);
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

it('rejects a cobro dated before the emision', function (): void {
    $factura = Factura::factory()->create(['fecha_emision' => '2026-09-01']);

    actingAs(actorConRol('contador'))
        ->patch(route('facturas.update', $factura), ['fecha_pago' => '2026-08-01'])
        ->assertSessionHasErrors('fecha_pago');
});

/**
 * Cuando las dos fechas se corrigen juntas, la regla tiene que medir contra la
 * emisión que viene en la misma petición. Mirando la guardada dejaba pasar un
 * pago anterior a la emisión, que es justo lo que quiere impedir.
 */
it('measures the cobro against the emision sent in the same request', function (): void {
    $factura = Factura::factory()->create(['fecha_emision' => '2026-09-01']);

    actingAs(actorConRol('contador'))
        ->patch(route('facturas.update', $factura), [
            'fecha_emision' => '2026-09-20',
            'fecha_pago' => '2026-09-10',
        ])
        ->assertSessionHasErrors('fecha_pago');

    expect($factura->fresh()->fecha_pago)->toBeNull();
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
        // Era su único viaje: se anula sola.
        ->and(Factura::query()->whereKey($estadia->id)->exists())->toBeFalse();
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
it('anula the factura when its last viaje is freed', function (): void {
    $factura = Factura::factory()->create();
    $viaje = Viaje::factory()->hasAttached($factura, relationship: 'facturas')->create();

    actingAs(actorConRol('contador'))
        ->delete(route('facturas.desvincular', [$factura, $viaje]))
        ->assertSessionHasNoErrors();

    expect(Factura::query()->count())->toBe(0)
        ->and($viaje->facturas()->exists())->toBeFalse();
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
