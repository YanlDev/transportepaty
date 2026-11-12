<?php

use App\Models\Cliente;
use App\Models\Conductor;
use App\Models\CuentaBancaria;
use App\Models\Factura;
use App\Models\Vehiculo;
use App\Models\Viaje;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    foreach (['admin', 'visor', 'conductor', 'contador'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

it('redirects guests to login', function (): void {
    $this->get(route('contabilidad.index'))->assertRedirect(route('login'));
});

it('lets the admin and the contador in, and keeps everyone else out', function (): void {
    actingAs(actorConRol('admin'))->get(route('contabilidad.index'))->assertSuccessful();
    actingAs(actorConRol('contador'))->get(route('contabilidad.index'))->assertSuccessful();

    // El visor ve la operación pero no los montos: es la razón de que la
    // cobranza viva en su propio módulo y no en columnas de `/viajes`.
    actingAs(actorConRol('visor'))->get(route('contabilidad.index'))->assertForbidden();
    actingAs(actorConRol('conductor'))->get(route('contabilidad.index'))->assertForbidden();
});

/**
 * La cobranza muestra la misma tabla que `/viajes`, no un resumen: se factura
 * contra el viaje entero, con su placa, su conductor y su carga a la vista.
 */
it('carries the same operativo columns as the viajes list', function (): void {
    $tracto = Vehiculo::factory()->create(['placa' => 'VDS730']);
    $conductor = Conductor::factory()->create();
    Viaje::factory()->create([
        'placa_tracto' => 'VDS730',
        'tracto_id' => $tracto->id,
        'conductor_id' => $conductor->id,
    ]);

    actingAs(actorConRol('contador'))
        ->get(route('contabilidad.index'))
        ->assertInertia(fn ($page) => $page->has('viajes.data.0', fn ($fila) => $fila
            ->where('placa_tracto', 'VDS730')
            ->where('tracto_id', $tracto->id)
            ->where('conductor_id', $conductor->id)
            ->hasAll([
                'id',
                'numero_gr',
                'guias_remitente',
                'grupo_viaje',
                'fecha_traslado',
                'placa_carreta',
                'carreta_id',
                'conductor_nombre',
                'cliente',
                'remitente',
                'destinatario',
                'origen',
                'origen_ciudad',
                'destino',
                'destino_ciudad',
                'tipo_carga',
                'tipo_carga_label',
                'peso',
                'unidad_peso',
                'bienes_fiscalizados',
                'falta_peso',
                'archivo_url',
                'gr_fisica_recibida_at',
                'estado',
                'estado_label',
                'motivo_no_facturable',
                'facturas',
            ])
        ));
});

/**
 * Una factura registrada sin monto suma cero y desaparecería del total sin que
 * nadie lo note; se cuenta aparte para que se vea.
 */
it('counts the facturas that still have no monto', function (): void {
    Viaje::factory()->hasAttached(Factura::factory()->create(['monto' => null]), relationship: 'facturas')->create();

    actingAs(actorConRol('contador'))
        ->get(route('contabilidad.index'))
        ->assertInertia(fn ($page) => $page
            ->where('resumen.sin_monto', 1)
            ->where('viajes.data.0.facturas.0.monto', null)
        );
});

it('marks a viaje without factura as sin facturar', function (): void {
    Viaje::factory()->create();

    actingAs(actorConRol('contador'))
        ->get(route('contabilidad.index'))
        ->assertInertia(fn ($page) => $page
            ->where('viajes.data.0.estado', 'sin_facturar')
            ->has('viajes.data.0.facturas', 0)
            ->where('resumen.por_facturar.0.viajes', 1)
        );
});

it('reports a viaje as por cobrar until the factura has a fecha de pago', function (): void {
    $factura = Factura::factory()->create(['monto' => 4500.50]);
    Viaje::factory()->hasAttached($factura, relationship: 'facturas')->create();

    actingAs(actorConRol('contador'))
        ->get(route('contabilidad.index'))
        ->assertInertia(fn ($page) => $page
            ->where('viajes.data.0.estado', 'facturado')
            // El flete descompuesto como lo imprime SUNAT.
            ->where('viajes.data.0.facturas.0.monto', 4500.50)
            ->where('viajes.data.0.facturas.0.igv', 810.09)
            ->where('viajes.data.0.facturas.0.total', 5310.59)
            ->where('viajes.data.0.facturas.0.detraccion', 212)
            ->where('viajes.data.0.facturas.0.neto', 5098.59)
            // Por cobrar es lo que falta depositar: el neto a la empresa y la
            // detracción al Banco de la Nación, por separado.
            ->where('resumen.montos.0.por_cobrar', 5098.59)
            ->where('resumen.montos.0.detraccion_por_cobrar', 212)
            ->where('resumen.montos.0.cobrado', 0)
        );
});

it('sends the cliente facturado with each factura', function (): void {
    $factura = Factura::factory()->create(['cliente_razon_social' => 'MINSUR S. A.', 'cliente_ruc' => '20100136741']);
    Viaje::factory()->hasAttached($factura, relationship: 'facturas')->create();

    actingAs(actorConRol('contador'))
        ->get(route('contabilidad.index'))
        ->assertInertia(fn ($page) => $page
            ->where('viajes.data.0.facturas.0.cliente', 'MINSUR S. A.')
            ->where('viajes.data.0.facturas.0.cliente_ruc', '20100136741')
        );
});

it('reports a viaje as pagado once both the neto and the detraccion came in', function (): void {
    $cuenta = CuentaBancaria::factory()->create(['alias' => 'BCP Soles']);
    $factura = Factura::factory()->create([
        'monto' => 3200.75,
        'fecha_emision' => '2026-08-25',
        'fecha_pago' => '2026-09-01',
        'cuenta_bancaria_id' => $cuenta->id,
        'fecha_detraccion' => '2026-09-03',
    ]);
    Viaje::factory()->hasAttached($factura, relationship: 'facturas')->create();

    actingAs(actorConRol('contador'))
        ->get(route('contabilidad.index'))
        ->assertInertia(fn ($page) => $page
            ->where('viajes.data.0.estado', 'pagado')
            ->where('viajes.data.0.facturas.0.cuenta', 'BCP Soles')
            // Cobrado es todo lo que entró: neto 3625.89 + detracción 151.
            ->where('resumen.montos.0.cobrado', 3776.89)
            ->where('resumen.montos.0.por_cobrar', 0)
            ->where('resumen.montos.0.detraccion_por_cobrar', 0)
        );
});

/**
 * El caso que motivó el módulo: dos GR de una misma salida se cobran una vez.
 * Si el total sumara por fila en vez de por factura, acá diría 9000.
 */
it('counts a factura once in the totals even when it covers several viajes', function (): void {
    $factura = Factura::factory()->create(['monto' => 4500.50]);
    Viaje::factory()->count(3)->hasAttached($factura, relationship: 'facturas')->create();

    actingAs(actorConRol('contador'))
        ->get(route('contabilidad.index'))
        ->assertInertia(fn ($page) => $page
            ->where('resumen.montos.0.por_cobrar', 5098.59)
            ->where('resumen.facturas', 1)
            ->where('viajes.data.0.facturas.0.viajes_count', 3)
        );
});

/**
 * Flete y estadía de una misma GR: la GR sigue por cobrar mientras falte
 * cobrar cualquiera de las dos, y ambas suman en el total porque son cobros
 * distintos.
 */
it('keeps a viaje por cobrar until every one of its facturas is paid', function (): void {
    $flete = Factura::factory()->create(['monto' => 4000, 'fecha_emision' => '2026-09-01', 'fecha_pago' => '2026-09-20', 'fecha_detraccion' => '2026-09-20']);
    $estadia = Factura::factory()->create(['monto' => 500, 'fecha_emision' => '2026-09-05']);
    Viaje::factory()
        ->hasAttached($flete, relationship: 'facturas')
        ->hasAttached($estadia, relationship: 'facturas')
        ->create(['numero_gr' => 'EG03-DOSFACTURAS']);

    actingAs(actorConRol('contador'))
        ->get(route('contabilidad.index'))
        ->assertInertia(fn ($page) => $page
            ->where('viajes.data.0.estado', 'facturado')
            ->has('viajes.data.0.facturas', 2)
            ->where('viajes.data.0.facturas.0.id', $flete->id)
            ->where('viajes.data.0.facturas.1.id', $estadia->id)
            ->where('resumen.facturas', 2)
            // Flete: total 4720 cobrado. Estadía: neto 566 + detracción 24.
            ->where('resumen.montos.0.cobrado', 4720)
            ->where('resumen.montos.0.por_cobrar', 566)
            ->where('resumen.montos.0.detraccion_por_cobrar', 24)
        );

    actingAs(actorConRol('contador'))
        ->get(route('contabilidad.index', ['estado' => 'pagado']))
        ->assertInertia(fn ($page) => $page->has('viajes.data', 0));

    $estadia->update(['fecha_pago' => '2026-09-25', 'fecha_detraccion' => '2026-09-25']);

    actingAs(actorConRol('contador'))
        ->get(route('contabilidad.index', ['estado' => 'pagado']))
        ->assertInertia(fn ($page) => $page
            ->has('viajes.data', 1)
            ->where('viajes.data.0.estado', 'pagado')
        );
});

it('keeps soles and dolares apart in the totals', function (): void {
    Viaje::factory()->hasAttached(Factura::factory()->create(['monto' => 1000, 'moneda' => 'PEN']), relationship: 'facturas')->create();
    Viaje::factory()->hasAttached(Factura::factory()->create(['monto' => 500, 'moneda' => 'USD']), relationship: 'facturas')->create();

    actingAs(actorConRol('contador'))
        ->get(route('contabilidad.index'))
        ->assertInertia(fn ($page) => $page->has('resumen.montos', 2));
});

it('filters by estado de cobranza', function (): void {
    Viaje::factory()->create(['numero_gr' => 'EG03-SINFACT']);
    Viaje::factory()->hasAttached(Factura::factory()->create(), relationship: 'facturas')->create([
        'numero_gr' => 'EG03-PORCOBRAR',
    ]);
    Viaje::factory()->hasAttached(Factura::factory()->faltaDetraccion()->create(), relationship: 'facturas')->create([
        'numero_gr' => 'EG03-FALTADETRACCION',
    ]);
    Viaje::factory()->hasAttached(Factura::factory()->pagada()->create(), relationship: 'facturas')->create([
        'numero_gr' => 'EG03-PAGADO',
    ]);

    foreach ([
        'sin_facturar' => 'EG03-SINFACT',
        'facturado' => 'EG03-PORCOBRAR',
        'falta_detraccion' => 'EG03-FALTADETRACCION',
        'pagado' => 'EG03-PAGADO',
    ] as $estado => $esperado) {
        actingAs(actorConRol('contador'))
            ->get(route('contabilidad.index', ['estado' => $estado]))
            ->assertInertia(fn ($page) => $page
                ->has('viajes.data', 1)
                ->where('viajes.data.0.numero_gr', $esperado)
            );
    }
});

it('finds a viaje by the numero of its factura', function (): void {
    $factura = Factura::factory()->create(['numero' => 'F001-00987']);
    Viaje::factory()->hasAttached($factura, relationship: 'facturas')->create(['numero_gr' => 'EG03-CONFACTURA']);
    Viaje::factory()->create(['numero_gr' => 'EG03-OTRO']);

    actingAs(actorConRol('contador'))
        ->get(route('contabilidad.index', ['buscar' => 'F001-00987']))
        ->assertInertia(fn ($page) => $page
            ->has('viajes.data', 1)
            ->where('viajes.data.0.numero_gr', 'EG03-CONFACTURA')
        );
});

/**
 * Un solo número global no sirve cuando arrastra medio año: lo que se mira es
 * cuánto de cada mes queda por facturar.
 */
it('breaks down what is left to factura by month, newest first', function (): void {
    Viaje::factory()->count(2)->create(['fecha_traslado' => '2026-09-05']);
    Viaje::factory()->create(['fecha_traslado' => '2026-08-05']);
    // Ya facturado: no cuenta como pendiente.
    Viaje::factory()->hasAttached(Factura::factory()->create(), relationship: 'facturas')->create([
        'fecha_traslado' => '2026-09-20',
    ]);

    actingAs(actorConRol('contador'))
        ->get(route('contabilidad.index'))
        ->assertInertia(fn ($page) => $page
            ->has('resumen.por_facturar', 2)
            ->where('resumen.por_facturar.0.mes', '2026-09')
            ->where('resumen.por_facturar.0.label', 'Septiembre 2026')
            ->where('resumen.por_facturar.0.viajes', 2)
            ->where('resumen.por_facturar.1.mes', '2026-08')
            ->where('resumen.por_facturar.1.viajes', 1)
        );
});

it('leaves the por facturar breakdown empty once everything is billed', function (): void {
    Viaje::factory()->hasAttached(Factura::factory()->create(), relationship: 'facturas')->create();

    actingAs(actorConRol('contador'))
        ->get(route('contabilidad.index'))
        ->assertInertia(fn ($page) => $page->has('resumen.por_facturar', 0));
});

it('offers the months that actually have viajes, newest first', function (): void {
    Viaje::factory()->create(['fecha_traslado' => '2026-07-10']);
    Viaje::factory()->create(['fecha_traslado' => '2026-09-02']);
    Viaje::factory()->create(['fecha_traslado' => '2026-09-20']);

    actingAs(actorConRol('contador'))
        ->get(route('contabilidad.index'))
        ->assertInertia(fn ($page) => $page
            // Dos meses, no tres: septiembre aparece una sola vez.
            ->has('meses', 2)
            ->where('meses.0.value', '2026-09')
            // En español, aunque el locale de la app esté en inglés.
            ->where('meses.0.label', 'Septiembre 2026')
            ->where('meses.1.value', '2026-07')
        );
});

it('filters by a whole month', function (): void {
    Viaje::factory()->create(['numero_gr' => 'EG03-AGOSTO', 'fecha_traslado' => '2026-08-31']);
    Viaje::factory()->create(['numero_gr' => 'EG03-SETIEMBRE', 'fecha_traslado' => '2026-09-01']);
    Viaje::factory()->create(['numero_gr' => 'EG03-OCTUBRE', 'fecha_traslado' => '2026-10-01']);

    actingAs(actorConRol('contador'))
        ->get(route('contabilidad.index', ['mes' => '2026-09']))
        ->assertInertia(fn ($page) => $page
            ->has('viajes.data', 1)
            ->where('viajes.data.0.numero_gr', 'EG03-SETIEMBRE')
        );
});

/**
 * El total tiene que responder al mes elegido: es la lectura de «cuánto me
 * queda por cobrar de setiembre», que es para lo que se filtra.
 */
it('narrows the totals to the selected month', function (): void {
    Viaje::factory()->hasAttached(Factura::factory()->create(['monto' => 1000.50]), relationship: 'facturas')->create([
        'fecha_traslado' => '2026-09-05',
    ]);
    Viaje::factory()->hasAttached(Factura::factory()->create(['monto' => 9999]), relationship: 'facturas')->create([
        'fecha_traslado' => '2026-08-05',
    ]);

    actingAs(actorConRol('contador'))
        ->get(route('contabilidad.index', ['mes' => '2026-09']))
        ->assertInertia(fn ($page) => $page
            ->where('resumen.montos.0.por_cobrar', 1133.59)
            ->where('resumen.facturas', 1)
        );
});

it('filters by fecha de traslado', function (): void {
    Viaje::factory()->create(['numero_gr' => 'EG03-VIEJO', 'fecha_traslado' => '2026-01-15']);
    Viaje::factory()->create(['numero_gr' => 'EG03-NUEVO', 'fecha_traslado' => '2026-08-15']);

    actingAs(actorConRol('contador'))
        ->get(route('contabilidad.index', ['desde' => '2026-08-01', 'hasta' => '2026-08-31']))
        ->assertInertia(fn ($page) => $page
            ->has('viajes.data', 1)
            ->where('viajes.data.0.numero_gr', 'EG03-NUEVO')
        );
});

it('only offers active cuentas for a new cobro', function (): void {
    CuentaBancaria::factory()->create(['alias' => 'BCP Soles']);
    CuentaBancaria::factory()->inactiva()->create(['alias' => 'Scotiabank cerrada']);

    actingAs(actorConRol('contador'))
        ->get(route('contabilidad.index'))
        ->assertInertia(fn ($page) => $page
            ->has('cuentas', 1)
            ->where('cuentas.0.alias', 'BCP Soles')
        );
});

/**
 * El orden de la tabla no puede depender del id, que es el orden en que se
 * importaron las GR y no tiene relación con su correlativo: dentro de un
 * mismo día las filas salían salteadas (12413, 12412, 12410, 12409, 12414).
 */
it('orders by fecha and then by the GR correlativo, not by import order', function (): void {
    // Se crean con el correlativo desordenado a propósito, para que el id y el
    // N° de GR no coincidan en orden.
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

    actingAs(actorConRol('contador'))
        ->get(route('contabilidad.index'))
        ->assertInertia(fn ($page) => $page
            // La fecha manda: el 10 va después del 11 aunque su GR sea mayor.
            ->where('viajes.data.0.numero_gr', 'EG03-00012414')
            ->where('viajes.data.1.numero_gr', 'EG03-00012410')
            ->where('viajes.data.2.numero_gr', 'EG03-00012409')
            ->where('viajes.data.3.numero_gr', 'EG03-00012500')
        );
});

it('marks and unmarks that the paper GR arrived at the office', function (): void {
    $viaje = Viaje::factory()->create();
    $contador = actorConRol('contador');

    actingAs($contador)
        ->patch(route('contabilidad.gr-fisica', $viaje), ['recibida' => true])
        ->assertRedirect();

    expect($viaje->fresh()->gr_fisica_recibida_at)->not->toBeNull();

    actingAs($contador)
        ->patch(route('contabilidad.gr-fisica', $viaje), ['recibida' => false])
        ->assertRedirect();

    expect($viaje->fresh()->gr_fisica_recibida_at)->toBeNull();
});

it('keeps the first fecha when the GR is marked again', function (): void {
    $viaje = Viaje::factory()->create(['gr_fisica_recibida_at' => '2026-09-10 09:00:00']);

    actingAs(actorConRol('contador'))
        ->patch(route('contabilidad.gr-fisica', $viaje), ['recibida' => true]);

    expect($viaje->fresh()->gr_fisica_recibida_at->toDateTimeString())->toBe('2026-09-10 09:00:00');
});

it('only lets the cobranza mark the paper GR', function (): void {
    $viaje = Viaje::factory()->create();

    actingAs(actorConRol('visor'))
        ->patch(route('contabilidad.gr-fisica', $viaje), ['recibida' => true])
        ->assertForbidden();

    expect($viaje->fresh()->gr_fisica_recibida_at)->toBeNull();
});

it('requires saying whether the paper GR arrived', function (): void {
    $viaje = Viaje::factory()->create();

    actingAs(actorConRol('contador'))
        ->patch(route('contabilidad.gr-fisica', $viaje), [])
        ->assertSessionHasErrors('recibida');
});

/**
 * La cajita de 0.2 TNE que viajó con la carga grande: no se cobra, así que
 * sale de lo pendiente sin anular la GR, que ante SUNAT sigue valiendo.
 */
it('takes a no facturable GR out of what is left to facturar', function (): void {
    $cajita = Viaje::factory()->create(['numero_gr' => 'EG03-CAJITA', 'fecha_traslado' => '2026-08-03']);
    Viaje::factory()->create(['numero_gr' => 'EG03-GRANDE', 'fecha_traslado' => '2026-08-03']);

    actingAs(actorConRol('contador'))
        ->patch(route('contabilidad.no-facturable', $cajita), ['no_facturable' => true, 'motivo' => 'Caja de 0.2 TNE'])
        ->assertSessionHasNoErrors();

    expect($cajita->refresh()->esNoFacturable())->toBeTrue()
        ->and($cajita->motivo_no_facturable)->toBe('Caja de 0.2 TNE');

    actingAs(actorConRol('contador'))
        ->get(route('contabilidad.index', ['estado' => 'sin_facturar']))
        ->assertInertia(fn ($page) => $page
            ->has('viajes.data', 1)
            ->where('viajes.data.0.numero_gr', 'EG03-GRANDE')
            ->where('resumen.por_facturar.0.viajes', 1)
        );

    actingAs(actorConRol('contador'))
        ->get(route('contabilidad.index', ['estado' => 'no_facturable']))
        ->assertInertia(fn ($page) => $page
            ->has('viajes.data', 1)
            ->where('viajes.data.0.estado', 'no_facturable')
            ->where('viajes.data.0.motivo_no_facturable', 'Caja de 0.2 TNE')
        );
});

it('puts a no facturable GR back in the cobranza', function (): void {
    $viaje = Viaje::factory()->create(['no_facturable_at' => now(), 'motivo_no_facturable' => 'Cortesía']);

    actingAs(actorConRol('contador'))
        ->patch(route('contabilidad.no-facturable', $viaje), ['no_facturable' => false])
        ->assertSessionHasNoErrors();

    expect($viaje->refresh()->esNoFacturable())->toBeFalse()
        ->and($viaje->motivo_no_facturable)->toBeNull();
});

it('refuses to mark a GR that is already facturada as no facturable', function (): void {
    $viaje = Viaje::factory()->hasAttached(Factura::factory()->create(), relationship: 'facturas')->create();

    actingAs(actorConRol('contador'))
        ->patch(route('contabilidad.no-facturable', $viaje), ['no_facturable' => true])
        ->assertSessionHasErrors('no_facturable');

    expect($viaje->refresh()->esNoFacturable())->toBeFalse();
});

it('keeps the visor from marking a GR as no facturable', function (): void {
    $viaje = Viaje::factory()->create();

    actingAs(actorConRol('visor'))
        ->patch(route('contabilidad.no-facturable', $viaje), ['no_facturable' => true])
        ->assertForbidden();
});

/**
 * La detracción que deposita la empresa no es por cobrar ni cobrada: va
 * aparte. Y la de una factura en dólares, que se deposita en soles, suma en
 * el bloque de soles.
 */
it('keeps apart the detraccion the company deposits and puts every detraccion in soles', function (): void {
    $cuenta = CuentaBancaria::factory()->create();
    $porEmpresa = Factura::factory()->create([
        'monto' => 7500,
        'detraccion_por_empresa' => true,
        'fecha_pago' => '2026-10-20',
        'cuenta_bancaria_id' => $cuenta->id,
    ]);
    $enDolares = Factura::factory()->create(['moneda' => 'USD']);
    $enDolares->forceFill(['monto' => '2324.56', 'igv' => '418.42', 'total' => '2742.98', 'detraccion' => '377.00', 'neto' => '2633.26', 'desde_pdf' => true])->saveQuietly();

    Viaje::factory()->hasAttached($porEmpresa, relationship: 'facturas')->create();
    Viaje::factory()->hasAttached($enDolares, relationship: 'facturas')->create();

    actingAs(actorConRol('contador'))
        ->get(route('contabilidad.index'))
        ->assertInertia(fn ($page) => $page
            ->where('resumen.montos.0.moneda', 'PEN')
            // El cliente pagó el total: eso es lo cobrado.
            ->where('resumen.montos.0.cobrado', 8850)
            ->where('resumen.montos.0.por_cobrar', 0)
            // Solo la de Minsur, en soles, la debe el cliente.
            ->where('resumen.montos.0.detraccion_por_cobrar', 377)
            ->where('resumen.montos.0.detraccion_por_depositar', 354)
            ->where('resumen.montos.1.moneda', 'USD')
            ->where('resumen.montos.1.por_cobrar', 2633.26)
            ->where('resumen.montos.1.detraccion_por_cobrar', 0)
        );
});

/**
 * Filtrar por la tienda a la que se le facturó encuentra el viaje aunque la
 * GR diga otro cliente, y la tienda aparece entre las opciones del filtro.
 */
it('filters by the client billed even when the GR names another one', function (): void {
    $factura = Factura::factory()->create(['cliente_razon_social' => 'CASAS CERAMICOS HATUN WASI E.I.R.L.']);
    Viaje::factory()->hasAttached($factura, relationship: 'facturas')
        ->create(['numero_gr' => 'EG03-SUPERPLAST', 'cliente' => 'INVERSIONES SUPERPLAST S.A.C.']);
    Viaje::factory()->create(['numero_gr' => 'EG03-OTRO', 'cliente' => 'MINSUR S.A.']);

    actingAs(actorConRol('contador'))
        ->get(route('contabilidad.index', ['cliente' => 'CASAS CERAMICOS HATUN WASI E.I.R.L.']))
        ->assertInertia(fn ($page) => $page
            ->has('viajes.data', 1)
            ->where('viajes.data.0.numero_gr', 'EG03-SUPERPLAST')
            ->where('clientes', fn ($clientes) => collect($clientes)->pluck('value')->contains('CASAS CERAMICOS HATUN WASI E.I.R.L.'))
        );
});

/**
 * Una factura con GR en dos meses es una sola plata: contarla entera en cada
 * mes la suma dos veces. Cada mes lleva la parte de sus GR, y los meses
 * vuelven a sumar el total.
 */
it('splits a factura across months by GR, so the months add up to the total', function (): void {
    // Valor 1000 → total 1180, detracción 47, neto 1133.
    $factura = Factura::factory()->create(['monto' => 1000]);
    Viaje::factory()->hasAttached($factura, relationship: 'facturas')->create(['fecha_traslado' => '2026-09-10']);
    Viaje::factory()->hasAttached($factura, relationship: 'facturas')->create(['fecha_traslado' => '2026-09-20']);
    Viaje::factory()->hasAttached($factura, relationship: 'facturas')->create(['fecha_traslado' => '2026-10-02']);

    actingAs(actorConRol('contador'));

    $this->get(route('contabilidad.index'))->assertInertia(fn ($page) => $page
        ->where('resumen.montos.0.por_cobrar', 1133)
        ->where('resumen.montos.0.detraccion_por_cobrar', 47)
        ->where('resumen.facturas', 1)
        ->where('resumen.repartidas', 0));

    $this->get(route('contabilidad.index', ['mes' => '2026-09']))->assertInertia(fn ($page) => $page
        ->where('resumen.montos.0.por_cobrar', 755.34)
        ->where('resumen.montos.0.detraccion_por_cobrar', 31.34)
        ->where('resumen.facturas', 1)
        ->where('resumen.repartidas', 1));

    $this->get(route('contabilidad.index', ['mes' => '2026-10']))->assertInertia(fn ($page) => $page
        ->where('resumen.montos.0.por_cobrar', 377.66)
        ->where('resumen.montos.0.detraccion_por_cobrar', 15.66)
        ->where('resumen.repartidas', 1));
});

it('splits what was already cobrado the same way', function (): void {
    $factura = Factura::factory()->pagada()->create(['monto' => 1000]);
    Viaje::factory()->hasAttached($factura, relationship: 'facturas')->create(['fecha_traslado' => '2026-09-10']);
    Viaje::factory()->hasAttached($factura, relationship: 'facturas')->create(['fecha_traslado' => '2026-10-02']);

    actingAs(actorConRol('contador'))
        ->get(route('contabilidad.index', ['mes' => '2026-09']))
        ->assertInertia(fn ($page) => $page
            ->where('resumen.montos.0.por_cobrar', 0)
            // La mitad de 1133 de neto más la mitad de 47 de detracción.
            ->where('resumen.montos.0.cobrado', 590));
});

/**
 * Por cliente no se reparte: la deuda es de a quien se le facturó, aunque una
 * de las GR nombre a otro. Se resuelve por RUC, porque el nombre de la GR y
 * el del PDF de la factura no siempre se escriben igual.
 */
it('counts a factura whole for the cliente it was billed to, and not for the other cliente of its GR', function (): void {
    $factura = Factura::factory()->create([
        'monto' => 1000,
        'cliente_ruc' => '20100000001',
        'cliente_razon_social' => 'ALFA S. A. C.',
    ]);
    Viaje::factory()->hasAttached($factura, relationship: 'facturas')->create(['cliente' => 'ALFA S.A.C.', 'cliente_ruc' => '20100000001']);
    Viaje::factory()->hasAttached($factura, relationship: 'facturas')->create(['cliente' => 'BETA S.A.C.', 'cliente_ruc' => '20200000002']);

    actingAs(actorConRol('contador'));

    $this->get(route('contabilidad.index', ['cliente' => 'ALFA S.A.C.']))->assertInertia(fn ($page) => $page
        ->where('resumen.montos.0.por_cobrar', 1133)
        ->where('resumen.facturas', 1)
        ->where('resumen.repartidas', 0));

    $this->get(route('contabilidad.index', ['cliente' => 'BETA S.A.C.']))->assertInertia(fn ($page) => $page
        ->where('resumen.montos', [])
        ->where('resumen.facturas', 0)
        // Ese viaje ya no es de Beta: se le facturó a Alfa.
        ->has('viajes.data', 0));
});

it('falls back to the cliente of the GR for a factura that does not say who was billed', function (): void {
    $factura = Factura::factory()->create(['monto' => 1000, 'cliente_ruc' => null, 'cliente_razon_social' => null]);
    Viaje::factory()->hasAttached($factura, relationship: 'facturas')->create(['cliente' => 'ALFA S.A.C.']);

    actingAs(actorConRol('contador'))
        ->get(route('contabilidad.index', ['cliente' => 'ALFA S.A.C.']))
        ->assertInertia(fn ($page) => $page->where('resumen.montos.0.por_cobrar', 1133));
});

/**
 * El cliente es el RUC, no el nombre: «MINSUR S.A.» en la GR y «MINSUR S. A.»
 * en el PDF de la factura son la misma empresa y salen una sola vez.
 */
it('offers one cliente per RUC, named by its alias del padrón', function (): void {
    Cliente::factory()->create(['ruc' => '20100136741', 'alias' => 'Minsur']);
    $factura = Factura::factory()->create(['cliente_ruc' => '20100136741', 'cliente_razon_social' => 'MINSUR S. A.']);
    Viaje::factory()->hasAttached($factura, relationship: 'facturas')->create(['cliente' => 'MINSUR S.A.', 'cliente_ruc' => '20100136741']);
    Viaje::factory()->create(['cliente' => 'MINSUR SA', 'cliente_ruc' => '20100136741']);
    // Remitente de una GR sin facturar y fuera del padrón: sale con su nombre.
    Viaje::factory()->create(['cliente' => 'BETA S.A.C.', 'cliente_ruc' => '20200000002']);
    // Sin RUC no hay con qué unificar: queda por nombre.
    Viaje::factory()->create(['cliente' => 'SIN RUC E.I.R.L.', 'cliente_ruc' => null]);

    actingAs(actorConRol('contador'))
        ->get(route('contabilidad.index'))
        ->assertInertia(fn ($page) => $page->where('clientes', [
            ['value' => '20200000002', 'label' => 'BETA S.A.C.'],
            ['value' => '20100136741', 'label' => 'Minsur'],
            ['value' => 'SIN RUC E.I.R.L.', 'label' => 'SIN RUC E.I.R.L.'],
        ]));
});

it('filters by RUC whatever way the cliente was written', function (): void {
    $factura = Factura::factory()->create(['monto' => 1000, 'cliente_ruc' => '20100136741', 'cliente_razon_social' => 'MINSUR S. A.']);
    Viaje::factory()->hasAttached($factura, relationship: 'facturas')->create(['cliente' => 'MINSUR S.A.', 'cliente_ruc' => '20100136741']);
    Viaje::factory()->create(['cliente' => 'MINSUR SA', 'cliente_ruc' => '20100136741']);
    Viaje::factory()->create(['cliente' => 'BETA S.A.C.', 'cliente_ruc' => '20200000002']);

    actingAs(actorConRol('contador'))
        ->get(route('contabilidad.index', ['cliente' => '20100136741']))
        ->assertInertia(fn ($page) => $page
            ->has('viajes.data', 2)
            ->where('resumen.montos.0.por_cobrar', 1133)
            ->where('resumen.facturas', 1));
});

it('counts a factura whole for the RUC it was billed to, and not for the RUC of its GR', function (): void {
    $factura = Factura::factory()->create(['monto' => 1000, 'cliente_ruc' => '20100000001', 'cliente_razon_social' => 'ALFA S.A.C.']);
    Viaje::factory()->hasAttached($factura, relationship: 'facturas')->create(['cliente' => 'BETA S.A.C.', 'cliente_ruc' => '20200000002']);

    actingAs(actorConRol('contador'));

    $this->get(route('contabilidad.index', ['cliente' => '20100000001']))->assertInertia(fn ($page) => $page
        ->where('resumen.montos.0.por_cobrar', 1133)
        ->has('viajes.data', 1));

    $this->get(route('contabilidad.index', ['cliente' => '20200000002']))->assertInertia(fn ($page) => $page
        ->where('resumen.montos', [])
        ->has('viajes.data', 0));
});

/**
 * Los clientes son a quienes se les factura: la migración da de alta los RUC
 * facturados que faltaban en el padrón, sin tocar los que ya estaban ni sumar
 * a los que solo figuran de remitente en una GR.
 */
it('adds to the padrón the clientes facturados that were missing', function (): void {
    $yaEstaba = Cliente::factory()->create(['ruc' => '20100136741', 'alias' => 'Minsur']);
    Factura::factory()->create(['cliente_ruc' => '20100136741', 'cliente_razon_social' => 'MINSUR S. A.']);
    Factura::factory()->create(['cliente_ruc' => '20607784966', 'cliente_razon_social' => 'MOLINORTE TRADING SAC', 'fecha_emision' => '2026-09-01']);
    Factura::factory()->create(['cliente_ruc' => '20607784966', 'cliente_razon_social' => 'MOLINORTE TRADING S.A.C.', 'fecha_emision' => '2026-10-01']);
    $gr = Viaje::factory()->create(['cliente' => 'MOLINORTE TRADING S.A.C.', 'cliente_ruc' => '20607784966', 'cliente_id' => null]);
    Viaje::factory()->create(['cliente' => 'SOLO REMITENTE S.A.C.', 'cliente_ruc' => '20999999999', 'cliente_id' => null]);

    (require database_path('migrations/2026_11_10_163247_da_de_alta_en_el_padron_a_los_clientes_facturados.php'))->up();

    $nuevo = Cliente::query()->where('ruc', '20607784966')->sole();

    expect(Cliente::query()->count())->toBe(2)
        ->and($nuevo->razon_social)->toBe('MOLINORTE TRADING S.A.C.')
        ->and($nuevo->alias)->toBe('Molinorte Trading')
        ->and($gr->fresh()->cliente_id)->toBe($nuevo->id)
        ->and($yaEstaba->fresh()->alias)->toBe('Minsur')
        ->and(Cliente::query()->where('ruc', '20999999999')->exists())->toBeFalse();
});

/**
 * Naturex despacha la carga, pero el flete se le factura a la tienda que la
 * recibe: el cliente de ese viaje es la tienda. Naturex queda como dato del
 * viaje (quién despachó), no como cliente, y deja de salir en el filtro.
 */
it('takes the cliente facturado as the cliente of the viaje, and drops who only dispatched', function (): void {
    Cliente::factory()->create(['ruc' => '10024024194', 'alias' => 'Paredes Tipo']);
    $factura = Factura::factory()->create(['cliente_ruc' => '10024024194', 'cliente_razon_social' => 'PAREDES TIPO DE OLIVERA LETICIA AURORA']);
    Viaje::factory()->hasAttached($factura, relationship: 'facturas')->create(['cliente' => 'NATUREX COMPANY SAC', 'cliente_ruc' => '20600000001']);

    actingAs(actorConRol('contador'))
        ->get(route('contabilidad.index'))
        ->assertInertia(fn ($page) => $page
            ->where('viajes.data.0.cliente', 'Paredes Tipo')
            ->where('viajes.data.0.remitente', 'NATUREX COMPANY SAC')
            ->where('clientes', [['value' => '10024024194', 'label' => 'Paredes Tipo']]));
});

it('keeps the cliente of the GR while the viaje has no factura', function (): void {
    Viaje::factory()->create(['cliente' => 'NATUREX COMPANY SAC', 'cliente_ruc' => '20600000001']);

    actingAs(actorConRol('contador'))
        ->get(route('contabilidad.index', ['cliente' => '20600000001']))
        ->assertInertia(fn ($page) => $page
            ->has('viajes.data', 1)
            ->where('viajes.data.0.cliente', 'NATUREX COMPANY SAC')
            ->where('viajes.data.0.remitente', null)
            ->where('clientes', [['value' => '20600000001', 'label' => 'NATUREX COMPANY SAC']]));
});

/**
 * Cobrada sin entidad: el resumen las cuenta y el filtro las trae, para
 * completarles el banco o marcar que fue en efectivo.
 */
it('counts and filters the facturas cobradas sin entidad', function (): void {
    $sinEntidad = Factura::factory()->create(['fecha_pago' => '2026-09-16', 'cuenta_bancaria_id' => null]);
    Viaje::factory()->hasAttached($sinEntidad, relationship: 'facturas')->create(['numero_gr' => 'EG03-00000001']);
    Viaje::factory()->hasAttached(Factura::factory()->pagada()->create(), relationship: 'facturas')->create();
    Viaje::factory()->hasAttached(Factura::factory()->pagada()->create(['cuenta_bancaria_id' => CuentaBancaria::factory()->caja()]), relationship: 'facturas')->create();
    Viaje::factory()->hasAttached(Factura::factory()->create(), relationship: 'facturas')->create();

    actingAs(actorConRol('contador'));

    $this->get(route('contabilidad.index'))->assertInertia(fn ($page) => $page
        ->where('resumen.sin_entidad', 1)
        ->where('estados', fn ($estados) => collect($estados)->contains('value', 'sin_entidad')));

    $this->get(route('contabilidad.index', ['estado' => 'sin_entidad']))->assertInertia(fn ($page) => $page
        ->has('viajes.data', 1)
        ->where('viajes.data.0.numero_gr', 'EG03-00000001')
        ->where('resumen.sin_entidad', 1));
});
