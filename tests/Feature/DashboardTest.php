<?php

use App\Enums\EstadoVehiculo;
use App\Enums\TipoCarga;
use App\Enums\TipoDocumento;
use App\Enums\TipoDocumentoConductor;
use App\Enums\TipoNovedad;
use App\Models\Conductor;
use App\Models\ConductorDocumento;
use App\Models\Novedad;
use App\Models\Vehiculo;
use App\Models\VehiculoDocumento;
use App\Models\Viaje;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    foreach (['admin', 'visor', 'conductor'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

it('redirects guests to login', function (): void {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

/**
 * El tablero junta la meta mensual, la mezcla de carga por cliente y el estado
 * documental de toda la flota. El rol `conductor` existe para que un chofer
 * consulte su unidad, no para leer la operación completa.
 */
it('keeps the conductor out of the tablero', function (): void {
    actingAs(actorConRol('conductor'))
        ->get(route('dashboard'))
        ->assertForbidden();
});

it('lets the admin, the visor and the contador see the tablero', function (): void {
    foreach (['admin', 'visor', 'contador'] as $rol) {
        actingAs(actorConRol($rol))
            ->get(route('dashboard'))
            ->assertSuccessful();
    }
});

it('summarises the fleet by type and status in the resumen tiles', function (): void {
    Vehiculo::factory()->count(2)->create(['estado' => EstadoVehiculo::Activo]);
    Vehiculo::factory()->create(['estado' => EstadoVehiculo::EnMantenimiento]);
    Vehiculo::factory()->carreta()->create();
    Conductor::factory()->count(3)->create();

    actingAs(actorConRol('admin'))
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('resumen.tractos', 3)
            ->where('resumen.carretas', 1)
            ->where('resumen.operativos', 3)
            ->where('resumen.conductores', 3)
            ->where('resumen.conductoresRegistrados', 3)
        );
});

it('counts expired documents across vehicles and drivers for the alert tile', function (): void {
    $vehiculo = Vehiculo::factory()->create();
    $conductor = Conductor::factory()->create();

    VehiculoDocumento::create([
        'vehiculo_id' => $vehiculo->id,
        'tipo' => TipoDocumento::Soat,
        'fecha_vencimiento' => now()->subDay()->toDateString(),
    ]);
    ConductorDocumento::create([
        'conductor_id' => $conductor->id,
        'tipo' => TipoDocumentoConductor::LicenciaConducir,
        'fecha_vencimiento' => now()->subWeek()->toDateString(),
    ]);
    VehiculoDocumento::create([
        'vehiculo_id' => $vehiculo->id,
        'tipo' => TipoDocumento::Matpel,
        'fecha_vencimiento' => now()->addMonths(6)->toDateString(),
    ]);

    actingAs(actorConRol('admin'))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('resumen.documentosVencidos', 2)
        );
});

it('counts active novedades for the not-schedulable tile', function (): void {
    Novedad::factory()->de(TipoNovedad::NoHabido)->create();
    Novedad::factory()->de(TipoNovedad::Taller)->create();
    // Ya levantada: no debe contar como vigente.
    Novedad::factory()->de(TipoNovedad::EnMina)->levantada(now()->toDateString())->create();

    actingAs(actorConRol('admin'))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('resumen.novedadesActivas', 2)
        );
});

it('breaks down Minsur cargo by tipo for the range, counting one per real trip not per GR', function (): void {
    $this->travelTo('2026-08-20 12:00:00');

    // Dos GR del mismo camión, mismo conductor, mismo día: es una sola
    // salida (ver `Viaje::claveGrupoViaje()`) y debe contar una sola vez.
    $primeraGr = Viaje::factory()->deMinsur()->tipoCarga(TipoCarga::Concentrado)->create(['fecha_traslado' => '2026-08-10']);
    Viaje::factory()->deMinsur()->tipoCarga(TipoCarga::Concentrado)->delMismoViajeQue($primeraGr)->create(['fecha_traslado' => '2026-08-10']);

    Viaje::factory()->deMinsur()->tipoCarga(TipoCarga::Metalico)->create(['fecha_traslado' => '2026-08-15']);

    // No es Minsur: no debe entrar en el desglose.
    Viaje::factory()->tipoCarga(TipoCarga::Concentrado)->create(['fecha_traslado' => '2026-08-15']);

    actingAs(actorConRol('admin'))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('cargaMinsur', fn ($tipos) => collect($tipos)
                ->firstWhere('tipo', TipoCarga::Concentrado->value)['valor'] === 1
                && collect($tipos)->firstWhere('tipo', TipoCarga::Metalico->value)['valor'] === 1
                && collect($tipos)->firstWhere('tipo', TipoCarga::Escoria->value)['valor'] === 0)
        );
});

it('matches Minsur regardless of the spacing variant in the razón social', function (): void {
    $this->travelTo('2026-08-20 12:00:00');

    Viaje::factory()->create(['cliente' => 'MINSUR S. A.', 'fecha_traslado' => '2026-08-10']);

    actingAs(actorConRol('admin'))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('cargaMinsur', fn ($tipos) => collect($tipos)
                ->firstWhere('tipo', TipoCarga::Particular->value)['valor'] === 1)
        );
});

it('defaults the range to the current month', function (): void {
    $this->travelTo('2026-08-20 12:00:00');

    Viaje::factory()->deMinsur()->tipoCarga(TipoCarga::Concentrado)->create(['fecha_traslado' => '2026-07-05']);
    Viaje::factory()->deMinsur()->tipoCarga(TipoCarga::Metalico)->create(['fecha_traslado' => '2026-08-12']);

    actingAs(actorConRol('admin'))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('rango.periodo', 'mes')
            ->where('rango.desde', '2026-08-01')
            ->where('rango.hasta', '2026-08-31')
            // Julio queda fuera del rango: solo cuenta el viaje de agosto.
            ->where('cargaMinsur', fn ($tipos) => collect($tipos)
                ->firstWhere('tipo', TipoCarga::Metalico->value)['valor'] === 1
                && collect($tipos)->firstWhere('tipo', TipoCarga::Concentrado->value)['valor'] === 0)
        );
});

it('widens the range to the last three months when asked', function (): void {
    $this->travelTo('2026-08-20 12:00:00');

    Viaje::factory()->deMinsur()->tipoCarga(TipoCarga::Concentrado)->create(['fecha_traslado' => '2026-06-05']);
    Viaje::factory()->deMinsur()->tipoCarga(TipoCarga::Metalico)->create(['fecha_traslado' => '2026-08-12']);

    actingAs(actorConRol('admin'))
        ->get(route('dashboard', ['periodo' => 'trimestre']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('rango.periodo', 'trimestre')
            ->where('rango.desde', '2026-06-01')
            ->where('rango.hasta', '2026-08-31')
            ->where('cargaMinsur', fn ($tipos) => collect($tipos)
                ->firstWhere('tipo', TipoCarga::Concentrado->value)['valor'] === 1
                && collect($tipos)->firstWhere('tipo', TipoCarga::Metalico->value)['valor'] === 1)
        );
});

it('falls back to the current month when the periodo is not one of the presets', function (): void {
    $this->travelTo('2026-08-20 12:00:00');

    actingAs(actorConRol('admin'))
        ->get(route('dashboard', ['periodo' => 'inventado']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('rango.periodo', 'mes')
            ->where('rango.desde', '2026-08-01')
        );
});

it('counts one trip for the same unit across two consecutive days (Mur-Wy case)', function (): void {
    $this->travelTo('2026-08-20 12:00:00');

    $primeraGr = Viaje::factory()->create(['cliente' => 'MUR - WY S.A.C.', 'fecha_traslado' => '2026-08-03']);
    Viaje::factory()->delMismoViajeQue($primeraGr)->create(['cliente' => 'MUR - WY S.A.C.', 'fecha_traslado' => '2026-08-03']);
    Viaje::factory()->delMismoViajeQue($primeraGr)->create(['cliente' => 'MUR - WY S.A.C.', 'fecha_traslado' => '2026-08-04']);

    actingAs(actorConRol('admin'))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('viajesPorCliente', fn ($clientes) => collect($clientes)
                ->firstWhere('cliente', 'MUR - WY S.A.C.')['valor'] === 1)
        );
});

it('counts trips per client with its share, Minsur included', function (): void {
    $this->travelTo('2026-08-20 12:00:00');

    $primeraGr = Viaje::factory()->create(['cliente' => 'CRISAR LOGISTICA S.A.C.', 'fecha_traslado' => '2026-08-05']);
    Viaje::factory()->delMismoViajeQue($primeraGr)->create(['cliente' => 'CRISAR LOGISTICA S.A.C.', 'fecha_traslado' => '2026-08-05']);

    Viaje::factory()->create(['cliente' => 'HOMECENTERS PERUANOS S.A.', 'fecha_traslado' => '2026-08-06']);
    Viaje::factory()->deMinsur()->create(['fecha_traslado' => '2026-08-07']);

    actingAs(actorConRol('admin'))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('viajesPorCliente', function ($clientes) {
                $clientes = collect($clientes);
                $minsur = $clientes->firstWhere('cliente', 'MINSUR S.A.');

                // Tres viajes reales en total: cada uno es un tercio.
                return $clientes->firstWhere('cliente', 'CRISAR LOGISTICA S.A.C.')['valor'] === 1
                    && $clientes->firstWhere('cliente', 'HOMECENTERS PERUANOS S.A.')['valor'] === 1
                    && $minsur['valor'] === 1
                    && $minsur['es_minsur'] === true
                    && $minsur['porcentaje'] === 33.3;
            })
        );
});

it('splits trips between Minsur and everyone else', function (): void {
    $this->travelTo('2026-08-20 12:00:00');

    Viaje::factory()->deMinsur()->create(['fecha_traslado' => '2026-08-05']);
    Viaje::factory()->deMinsur()->create(['fecha_traslado' => '2026-08-06']);
    Viaje::factory()->create(['cliente' => 'CRISAR LOGISTICA S.A.C.', 'fecha_traslado' => '2026-08-07']);

    actingAs(actorConRol('admin'))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('viajesPorTipoCliente.minsur', 2)
            ->where('viajesPorTipoCliente.particulares', 1)
            ->where('viajesPorTipoCliente.total', 3)
        );
});

it('buckets every document of the fleet by its expiry state', function (): void {
    $vehiculo = Vehiculo::factory()->create();
    $conductor = Conductor::factory()->create();

    VehiculoDocumento::create([
        'vehiculo_id' => $vehiculo->id,
        'tipo' => TipoDocumento::Soat,
        'fecha_vencimiento' => now()->addYear()->toDateString(),
    ]);
    VehiculoDocumento::create([
        'vehiculo_id' => $vehiculo->id,
        'tipo' => TipoDocumento::Matpel,
        'fecha_vencimiento' => now()->addDays(10)->toDateString(),
    ]);
    // Sin fecha: se cuenta aparte de los vigentes con vigencia comprobada.
    VehiculoDocumento::create([
        'vehiculo_id' => $vehiculo->id,
        'tipo' => TipoDocumento::TarjetaPropiedad,
        'fecha_vencimiento' => null,
    ]);
    ConductorDocumento::create([
        'conductor_id' => $conductor->id,
        'tipo' => TipoDocumentoConductor::LicenciaConducir,
        'fecha_vencimiento' => now()->subDay()->toDateString(),
    ]);

    actingAs(actorConRol('admin'))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('documentos.vigentes', 1)
            ->where('documentos.por_vencer', 1)
            ->where('documentos.vencidos', 1)
            ->where('documentos.sin_fecha', 1)
            ->where('documentos.total', 4)
        );
});

it('summarises how many units are available today', function (): void {
    $operativo = Vehiculo::factory()->create(['estado' => EstadoVehiculo::Activo]);
    Vehiculo::factory()->create(['estado' => EstadoVehiculo::EnMantenimiento]);

    VehiculoDocumento::create([
        'vehiculo_id' => $operativo->id,
        'tipo' => TipoDocumento::Soat,
        'fecha_vencimiento' => now()->subDay()->toDateString(),
    ]);

    Novedad::factory()->de(TipoNovedad::Taller)->create();

    actingAs(actorConRol('admin'))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            // La novedad trae su propio tracto (activo), así que son tres
            // unidades y dos operativas.
            ->where('unidades.operativas', 2)
            ->where('unidades.no_programables', 1)
            ->where('unidades.con_documentos_vencidos', 1)
            ->where('unidades.total', 3)
        );
});

it('lists the last registered trips regardless of the range', function (): void {
    $this->travelTo('2026-09-10 12:00:00');

    $viejo = Viaje::factory()->create(['fecha_traslado' => '2026-05-01']);
    $nuevo = Viaje::factory()->create(['fecha_traslado' => '2026-09-08']);

    actingAs(actorConRol('admin'))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('ultimosViajes', 2)
            ->where('ultimosViajes.0.id', $nuevo->id)
            ->where('ultimosViajes.1.id', $viejo->id)
        );
});

it('tracks progress toward the monthly concentrado goal for the current month, counting real trips not GR rows', function (): void {
    $this->travelTo('2026-08-10 12:00:00');

    // Dos GR de la misma salida: un solo viaje real.
    $primeraGr = Viaje::factory()->deMinsur()->tipoCarga(TipoCarga::Concentrado)->create(['fecha_traslado' => '2026-08-01']);
    Viaje::factory()->deMinsur()->tipoCarga(TipoCarga::Concentrado)->delMismoViajeQue($primeraGr)->create(['fecha_traslado' => '2026-08-01']);

    Viaje::factory()->deMinsur()->tipoCarga(TipoCarga::Concentrado)->create(['fecha_traslado' => '2026-08-05']);

    // Fuera del mes en curso o de otro tipo de carga: no debe contar.
    Viaje::factory()->deMinsur()->tipoCarga(TipoCarga::Concentrado)->create(['fecha_traslado' => '2026-07-20']);
    Viaje::factory()->deMinsur()->tipoCarga(TipoCarga::Metalico)->create(['fecha_traslado' => '2026-08-06']);

    actingAs(actorConRol('admin'))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('metaConcentrado.meta', 120)
            ->where('metaConcentrado.realizados', 2)
            ->where('metaConcentrado.faltantes', 118)
            ->where('metaConcentrado.diasRestantes', 21)
            ->where('metaConcentrado.proyeccion', 6)
            ->where('metaConcentrado.ritmoNecesario', 5.6)
        );
});

it('leaves ritmoNecesario null on the last day of the month', function (): void {
    $this->travelTo('2026-08-31 12:00:00');

    actingAs(actorConRol('admin'))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('metaConcentrado.diasRestantes', 0)
            ->where('metaConcentrado.ritmoNecesario', null)
        );
});

it('keeps persona natural clients in the same viajesPorCliente list as empresas', function (): void {
    $this->travelTo('2026-08-20 12:00:00');

    Viaje::factory()->create(['cliente' => 'GUZMAN REVILLA CHRISTOPHER CHRISTIAN', 'fecha_traslado' => '2026-08-05']);
    Viaje::factory()->create(['cliente' => 'CRISAR LOGISTICA S.A.C.', 'fecha_traslado' => '2026-08-06']);

    actingAs(actorConRol('admin'))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('viajesPorCliente', fn ($clientes) => collect($clientes)
                ->firstWhere('cliente', 'GUZMAN REVILLA CHRISTOPHER CHRISTIAN')['valor'] === 1
                && collect($clientes)->firstWhere('cliente', 'CRISAR LOGISTICA S.A.C.')['valor'] === 1)
        );
});

/**
 * Los bordes del semáforo, contados sobre la fecha de Lima: vence hoy es «por
 * vencer» (vale hasta la medianoche), el día 15 del aviso todavía es ámbar y
 * el 16 ya es vigente. Los papeles de un vehículo dado de baja no cuentan.
 */
it('counts document expiry boundaries the same way the fichas do', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-09-25 22:00', 'America/Lima'));

    $vencimientos = [
        '2026-09-24' => 'vencido',
        '2026-09-25' => 'hoy',
        '2026-10-10' => 'día 15',
        '2026-10-11' => 'día 16',
    ];

    foreach (array_keys($vencimientos) as $fecha) {
        VehiculoDocumento::create([
            'vehiculo_id' => Vehiculo::factory()->create()->id,
            'tipo' => TipoDocumento::Soat,
            'fecha_vencimiento' => $fecha,
        ]);
    }

    $deBaja = Vehiculo::factory()->create();
    VehiculoDocumento::create([
        'vehiculo_id' => $deBaja->id,
        'tipo' => TipoDocumento::Soat,
        'fecha_vencimiento' => '2026-09-01',
    ]);
    $deBaja->delete();

    ConductorDocumento::create([
        'conductor_id' => Conductor::factory()->create()->id,
        'tipo' => TipoDocumentoConductor::LicenciaConducir,
        'fecha_vencimiento' => '2026-10-10',
    ]);

    actingAs(actorConRol('admin'))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('documentos.vencidos', 1)
            ->where('documentos.por_vencer', 3)
            ->where('documentos.vigentes', 1)
            ->where('documentos.sin_fecha', 0)
            ->where('documentos.total', 5)
            ->where('resumen.documentosVencidos', 1)
        );
});
