<?php

use App\Enums\EstadoVehiculo;
use App\Enums\TipoDocumento;
use App\Models\Conductor;
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
