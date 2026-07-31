<?php

use App\Enums\EstadoVehiculo;
use App\Enums\FaseCiclo;
use App\Enums\TipoCarga;
use App\Enums\TipoDocumento;
use App\Enums\TipoDocumentoConductor;
use App\Enums\TipoNovedad;
use App\Models\Conductor;
use App\Models\ConductorDocumento;
use App\Models\EstadoUnidad;
use App\Models\Novedad;
use App\Models\Vehiculo;
use App\Models\VehiculoDocumento;
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

it('summarises the fleet by type and status', function (): void {
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
            ->has('documentosPorVencer')
            ->where('estadoFlota', fn ($estados) => collect($estados)
                ->firstWhere('estado', EstadoVehiculo::Activo->value)['valor'] === 3
                && collect($estados)->firstWhere('estado', EstadoVehiculo::EnMantenimiento->value)['valor'] === 1)
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

it('counts active novedades for the not-schedulable tile and grouped by tipo', function (): void {
    Novedad::factory()->de(TipoNovedad::NoHabido)->create();
    Novedad::factory()->de(TipoNovedad::Taller)->create();
    // Ya levantada: no debe contar como vigente.
    Novedad::factory()->de(TipoNovedad::EnMina)->levantada(now()->toDateString())->create();

    actingAs(actorConRol('admin'))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('resumen.novedadesActivas', 2)
            ->where('novedadesPorTipo', fn ($tipos) => collect($tipos)
                ->firstWhere('tipo', TipoNovedad::NoHabido->value)['valor'] === 1
                && collect($tipos)->firstWhere('tipo', TipoNovedad::Taller->value)['valor'] === 1
                && collect($tipos)->firstWhere('tipo', TipoNovedad::EnMina->value)['valor'] === 0)
        );
});

it('groups today’s cycle phase from the latest estado of each tracto', function (): void {
    $tracto = Vehiculo::factory()->create();

    EstadoUnidad::create([
        'tracto_id' => $tracto->id,
        'tipo_carga' => TipoCarga::Vacio,
        'fecha' => now()->subDays(2)->toDateString(),
    ]);
    EstadoUnidad::create([
        'tracto_id' => $tracto->id,
        'tipo_carga' => TipoCarga::Concentrado,
        'fecha' => now()->toDateString(),
    ]);

    actingAs(actorConRol('admin'))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('fasesCiclo', fn ($fases) => collect($fases)
                ->firstWhere('fase', FaseCiclo::MinaPisco->value)['valor'] === 1
                && collect($fases)->firstWhere('fase', FaseCiclo::SubidaMina->value)['valor'] === 0)
        );
});

it('breaks down documentation health by semáforo for vehicles and drivers', function (): void {
    $alDia = Vehiculo::factory()->create();
    $conProblemas = Vehiculo::factory()->create();

    foreach ($alDia->tipo->documentosObligatorios() as $tipo) {
        VehiculoDocumento::create([
            'vehiculo_id' => $alDia->id,
            'tipo' => $tipo,
            'fecha_vencimiento' => null,
        ]);
    }

    VehiculoDocumento::create([
        'vehiculo_id' => $conProblemas->id,
        'tipo' => TipoDocumento::Soat,
        'fecha_vencimiento' => now()->subDay()->toDateString(),
    ]);

    actingAs(actorConRol('admin'))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('saludDocumental', function ($salud) {
                $vehiculos = collect($salud)->firstWhere('entidad', 'vehiculos');

                return $vehiculos['rojo'] === 1 && $vehiculos['verde'] === 1;
            })
        );
});

it('surfaces a document about to expire', function (): void {
    $vehiculo = Vehiculo::factory()->create();

    VehiculoDocumento::create([
        'vehiculo_id' => $vehiculo->id,
        'tipo' => TipoDocumento::Soat,
        'fecha_vencimiento' => now()->addDays(10)->toDateString(),
    ]);

    actingAs(actorConRol('admin'))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('documentosPorVencer', 1)
            ->where('documentosPorVencer.0.tipo_label', 'SOAT')
            ->where('documentosPorVencer.0.vencido', false)
        );
});

it('flags an already expired document', function (): void {
    $vehiculo = Vehiculo::factory()->create();

    VehiculoDocumento::create([
        'vehiculo_id' => $vehiculo->id,
        'tipo' => TipoDocumento::Matpel,
        'fecha_vencimiento' => now()->subDay()->toDateString(),
    ]);

    actingAs(actorConRol('admin'))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('documentosPorVencer', 1)
            ->where('documentosPorVencer.0.vencido', true)
        );
});

it('ignores documents that expire far in the future', function (): void {
    $vehiculo = Vehiculo::factory()->create();

    VehiculoDocumento::create([
        'vehiculo_id' => $vehiculo->id,
        'tipo' => TipoDocumento::Soat,
        'fecha_vencimiento' => now()->addMonths(6)->toDateString(),
    ]);

    actingAs(actorConRol('admin'))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->has('documentosPorVencer', 0));
});

it('leaves out documents of vehicles that were deleted', function (): void {
    $vehiculo = Vehiculo::factory()->create();

    VehiculoDocumento::create([
        'vehiculo_id' => $vehiculo->id,
        'tipo' => TipoDocumento::Soat,
        'fecha_vencimiento' => now()->subDay()->toDateString(),
    ]);

    $vehiculo->delete();

    actingAs(actorConRol('admin'))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->has('documentosPorVencer', 0));
});

it('surfaces an expiring conductor licencia next to vehicle documents', function (): void {
    $conductor = Conductor::factory()->create();

    ConductorDocumento::create([
        'conductor_id' => $conductor->id,
        'tipo' => TipoDocumentoConductor::LicenciaConducir,
        'fecha_vencimiento' => now()->addDays(5)->toDateString(),
    ]);

    actingAs(actorConRol('admin'))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('documentosPorVencer', 1)
            ->where('documentosPorVencer.0.titular', $conductor->nombre_completo)
            ->where('documentosPorVencer.0.conductor_id', $conductor->id)
            ->where('documentosPorVencer.0.vencido', false)
        );
});

it('treats a document expiring today as por vencer, matching the semáforo', function (): void {
    $vehiculo = Vehiculo::factory()->create();

    VehiculoDocumento::create([
        'vehiculo_id' => $vehiculo->id,
        'tipo' => TipoDocumento::Soat,
        'fecha_vencimiento' => now()->toDateString(),
    ]);

    actingAs(actorConRol('admin'))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('documentosPorVencer', 1)
            ->where('documentosPorVencer.0.vencido', false)
        );
});

it('orders mixed vencimientos by urgency', function (): void {
    $vehiculo = Vehiculo::factory()->create();
    $conductor = Conductor::factory()->create();

    VehiculoDocumento::create([
        'vehiculo_id' => $vehiculo->id,
        'tipo' => TipoDocumento::Soat,
        'fecha_vencimiento' => now()->addDays(20)->toDateString(),
    ]);
    ConductorDocumento::create([
        'conductor_id' => $conductor->id,
        'tipo' => TipoDocumentoConductor::LicenciaConducir,
        'fecha_vencimiento' => now()->addDays(3)->toDateString(),
    ]);

    actingAs(actorConRol('admin'))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('documentosPorVencer', 2)
            ->where('documentosPorVencer.0.titular', $conductor->nombre_completo)
            ->where('documentosPorVencer.1.tipo_label', 'SOAT')
        );
});

it('narrows the vencimientos window to 15 days', function (): void {
    $vehiculo = Vehiculo::factory()->create();

    VehiculoDocumento::create([
        'vehiculo_id' => $vehiculo->id,
        'tipo' => TipoDocumento::Soat,
        'fecha_vencimiento' => now()->addDays(10)->toDateString(),
    ]);
    VehiculoDocumento::create([
        'vehiculo_id' => $vehiculo->id,
        'tipo' => TipoDocumento::Matpel,
        'fecha_vencimiento' => now()->addDays(25)->toDateString(),
    ]);

    actingAs(actorConRol('admin'))
        ->get(route('dashboard', ['dias' => 15]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filtros.dias', 15)
            ->has('documentosPorVencer', 1)
            ->where('documentosPorVencer.0.tipo_label', 'SOAT')
        );
});

it('keeps already expired documents visible on the shorter window', function (): void {
    $vehiculo = Vehiculo::factory()->create();

    VehiculoDocumento::create([
        'vehiculo_id' => $vehiculo->id,
        'tipo' => TipoDocumento::Soat,
        'fecha_vencimiento' => now()->subMonths(3)->toDateString(),
    ]);

    actingAs(actorConRol('admin'))
        ->get(route('dashboard', ['dias' => 15]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('documentosPorVencer', 1)
            ->where('documentosPorVencer.0.vencido', true)
        );
});

it('falls back to 30 days when the filter value is not offered', function (): void {
    $vehiculo = Vehiculo::factory()->create();

    VehiculoDocumento::create([
        'vehiculo_id' => $vehiculo->id,
        'tipo' => TipoDocumento::Soat,
        'fecha_vencimiento' => now()->addDays(25)->toDateString(),
    ]);

    actingAs(actorConRol('admin'))
        ->get(route('dashboard', ['dias' => 999]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filtros.dias', 30)
            ->has('documentosPorVencer', 1)
        );
});
