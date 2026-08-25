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

it('breaks down Minsur cargo by tipo for the selected month, counting one per real trip not per GR', function (): void {
    // Dos GR del mismo camión, mismo conductor, mismo día: es una sola
    // salida (ver `Viaje::claveGrupoViaje()`) y debe contar una sola vez.
    $primeraGr = Viaje::factory()->deMinsur()->tipoCarga(TipoCarga::Concentrado)->create(['fecha_traslado' => '2026-08-10']);
    Viaje::factory()->deMinsur()->tipoCarga(TipoCarga::Concentrado)->delMismoViajeQue($primeraGr)->create(['fecha_traslado' => '2026-08-10']);

    Viaje::factory()->deMinsur()->tipoCarga(TipoCarga::Metalico)->create(['fecha_traslado' => '2026-08-15']);

    // No es Minsur: no debe entrar en el desglose.
    Viaje::factory()->tipoCarga(TipoCarga::Concentrado)->create(['fecha_traslado' => '2026-08-15']);

    actingAs(actorConRol('admin'))
        ->get(route('dashboard', ['mes' => '2026-08']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('cargaMinsur', fn ($tipos) => collect($tipos)
                ->firstWhere('tipo', TipoCarga::Concentrado->value)['valor'] === 1
                && collect($tipos)->firstWhere('tipo', TipoCarga::Metalico->value)['valor'] === 1
                && collect($tipos)->firstWhere('tipo', TipoCarga::Escoria->value)['valor'] === 0)
        );
});

it('matches Minsur regardless of the spacing variant in the razón social', function (): void {
    Viaje::factory()->create(['cliente' => 'MINSUR S. A.', 'fecha_traslado' => '2026-08-10']);

    actingAs(actorConRol('admin'))
        ->get(route('dashboard', ['mes' => '2026-08']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('cargaMinsur', fn ($tipos) => collect($tipos)
                ->firstWhere('tipo', TipoCarga::Particular->value)['valor'] === 1)
        );
});

it('filters cargaMinsur to the requested month and offers the available months', function (): void {
    Viaje::factory()->deMinsur()->tipoCarga(TipoCarga::Concentrado)->create(['fecha_traslado' => '2026-07-05']);
    Viaje::factory()->deMinsur()->tipoCarga(TipoCarga::Metalico)->create(['fecha_traslado' => '2026-08-12']);

    actingAs(actorConRol('admin'))
        ->get(route('dashboard', ['mes' => '2026-07']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filtroMes', '2026-07')
            ->where('mesesDisponibles', ['2026-08', '2026-07'])
            ->where('cargaMinsur', fn ($tipos) => collect($tipos)
                ->firstWhere('tipo', TipoCarga::Concentrado->value)['valor'] === 1
                && collect($tipos)->firstWhere('tipo', TipoCarga::Metalico->value)['valor'] === 0)
        );
});

it('defaults cargaMinsur to the most recent month when none is requested', function (): void {
    Viaje::factory()->deMinsur()->tipoCarga(TipoCarga::Concentrado)->create(['fecha_traslado' => '2026-07-05']);
    Viaje::factory()->deMinsur()->tipoCarga(TipoCarga::Metalico)->create(['fecha_traslado' => '2026-08-12']);

    actingAs(actorConRol('admin'))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filtroMes', '2026-08')
            ->where('cargaMinsur', fn ($tipos) => collect($tipos)
                ->firstWhere('tipo', TipoCarga::Metalico->value)['valor'] === 1
                && collect($tipos)->firstWhere('tipo', TipoCarga::Concentrado->value)['valor'] === 0)
        );
});

it('counts one trip for the same unit across two consecutive days (Mur-Wy case)', function (): void {
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

it('counts trips per other client, one per real trip not per GR, excluding Minsur', function (): void {
    $primeraGr = Viaje::factory()->create(['cliente' => 'CRISAR LOGISTICA S.A.C.']);
    Viaje::factory()->delMismoViajeQue($primeraGr)->create(['cliente' => 'CRISAR LOGISTICA S.A.C.']);

    Viaje::factory()->create(['cliente' => 'HOMECENTERS PERUANOS S.A.']);

    Viaje::factory()->deMinsur()->create();

    actingAs(actorConRol('admin'))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('viajesPorCliente', function ($clientes) {
                $clientes = collect($clientes);

                return $clientes->firstWhere('cliente', 'CRISAR LOGISTICA S.A.C.')['valor'] === 1
                    && $clientes->firstWhere('cliente', 'HOMECENTERS PERUANOS S.A.')['valor'] === 1
                    && $clientes->firstWhere('cliente', 'MINSUR S.A.') === null;
            })
        );
});

it('keeps persona natural clients in the same viajesPorCliente list as empresas', function (): void {
    Viaje::factory()->create(['cliente' => 'GUZMAN REVILLA CHRISTOPHER CHRISTIAN']);
    Viaje::factory()->create(['cliente' => 'CRISAR LOGISTICA S.A.C.']);

    actingAs(actorConRol('admin'))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('viajesPorCliente', fn ($clientes) => collect($clientes)
                ->firstWhere('cliente', 'GUZMAN REVILLA CHRISTOPHER CHRISTIAN')['valor'] === 1
                && collect($clientes)->firstWhere('cliente', 'CRISAR LOGISTICA S.A.C.')['valor'] === 1)
        );
});
