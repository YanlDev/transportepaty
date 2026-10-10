<?php

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
