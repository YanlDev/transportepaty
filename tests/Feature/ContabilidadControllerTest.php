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
                'estado',
                'estado_label',
                'factura',
            ])
        ));
});

/**
 * Una factura registrada sin monto suma cero y desaparecería del total sin que
 * nadie lo note; se cuenta aparte para que se vea.
 */
it('counts the facturas that still have no monto', function (): void {
    Viaje::factory()->create([
        'factura_id' => Factura::factory()->create(['monto' => null])->id,
    ]);

    actingAs(actorConRol('contador'))
        ->get(route('contabilidad.index'))
        ->assertInertia(fn ($page) => $page
            ->where('resumen.sin_monto', 1)
            ->where('viajes.data.0.factura.monto', null)
        );
});

it('marks a viaje without factura as sin facturar', function (): void {
    Viaje::factory()->create();

    actingAs(actorConRol('contador'))
        ->get(route('contabilidad.index'))
        ->assertInertia(fn ($page) => $page
            ->where('viajes.data.0.estado', 'sin_facturar')
            ->where('viajes.data.0.factura', null)
            ->where('resumen.por_facturar.0.viajes', 1)
        );
});

it('reports a viaje as por cobrar until the factura has a fecha de pago', function (): void {
    $factura = Factura::factory()->create(['monto' => 4500.50]);
    Viaje::factory()->create(['factura_id' => $factura->id]);

    actingAs(actorConRol('contador'))
        ->get(route('contabilidad.index'))
        ->assertInertia(fn ($page) => $page
            ->where('viajes.data.0.estado', 'facturado')
            ->where('viajes.data.0.factura.monto', 4500.50)
            ->where('resumen.montos.0.por_cobrar', 4500.50)
            ->where('resumen.montos.0.cobrado', 0)
        );
});

it('reports a viaje as pagado once the factura has a fecha de pago', function (): void {
    $cuenta = CuentaBancaria::factory()->create(['alias' => 'BCP Soles']);
    $factura = Factura::factory()->create([
        'monto' => 3200.75,
        'fecha_pago' => '2026-09-01',
        'cuenta_bancaria_id' => $cuenta->id,
    ]);
    Viaje::factory()->create(['factura_id' => $factura->id]);

    actingAs(actorConRol('contador'))
        ->get(route('contabilidad.index'))
        ->assertInertia(fn ($page) => $page
            ->where('viajes.data.0.estado', 'pagado')
            ->where('viajes.data.0.factura.cuenta', 'BCP Soles')
            ->where('resumen.montos.0.cobrado', 3200.75)
            ->where('resumen.montos.0.por_cobrar', 0)
        );
});

/**
 * El caso que motivó el módulo: dos GR de una misma salida se cobran una vez.
 * Si el total sumara por fila en vez de por factura, acá diría 9000.
 */
it('counts a factura once in the totals even when it covers several viajes', function (): void {
    $factura = Factura::factory()->create(['monto' => 4500.50]);
    Viaje::factory()->count(3)->create(['factura_id' => $factura->id]);

    actingAs(actorConRol('contador'))
        ->get(route('contabilidad.index'))
        ->assertInertia(fn ($page) => $page
            ->where('resumen.montos.0.por_cobrar', 4500.50)
            ->where('resumen.facturas', 1)
            ->where('viajes.data.0.factura.viajes_count', 3)
        );
});

it('keeps soles and dolares apart in the totals', function (): void {
    Viaje::factory()->create([
        'factura_id' => Factura::factory()->create(['monto' => 1000, 'moneda' => 'PEN'])->id,
    ]);
    Viaje::factory()->create([
        'factura_id' => Factura::factory()->create(['monto' => 500, 'moneda' => 'USD'])->id,
    ]);

    actingAs(actorConRol('contador'))
        ->get(route('contabilidad.index'))
        ->assertInertia(fn ($page) => $page->has('resumen.montos', 2));
});

it('filters by estado de cobranza', function (): void {
    Viaje::factory()->create(['numero_gr' => 'EG03-SINFACT']);
    Viaje::factory()->create([
        'numero_gr' => 'EG03-PORCOBRAR',
        'factura_id' => Factura::factory()->create()->id,
    ]);
    Viaje::factory()->create([
        'numero_gr' => 'EG03-PAGADO',
        'factura_id' => Factura::factory()->pagada()->create()->id,
    ]);

    foreach ([
        'sin_facturar' => 'EG03-SINFACT',
        'facturado' => 'EG03-PORCOBRAR',
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
    Viaje::factory()->create(['numero_gr' => 'EG03-CONFACTURA', 'factura_id' => $factura->id]);
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
    Viaje::factory()->create([
        'fecha_traslado' => '2026-09-20',
        'factura_id' => Factura::factory()->create()->id,
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
    Viaje::factory()->create(['factura_id' => Factura::factory()->create()->id]);

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
    Viaje::factory()->create([
        'fecha_traslado' => '2026-09-05',
        'factura_id' => Factura::factory()->create(['monto' => 1000.50])->id,
    ]);
    Viaje::factory()->create([
        'fecha_traslado' => '2026-08-05',
        'factura_id' => Factura::factory()->create(['monto' => 9999])->id,
    ]);

    actingAs(actorConRol('contador'))
        ->get(route('contabilidad.index', ['mes' => '2026-09']))
        ->assertInertia(fn ($page) => $page
            ->where('resumen.montos.0.por_cobrar', 1000.50)
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
