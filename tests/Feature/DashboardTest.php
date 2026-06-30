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

it('shows the dashboard with KPIs and panels to an admin', function (): void {
    Vehiculo::factory()->count(2)->create(['estado' => EstadoVehiculo::Activo]);
    Vehiculo::factory()->create(['estado' => EstadoVehiculo::EnMantenimiento]);

    actingAs(actorConRol('admin'))
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('esGestor', true)
            ->where('kpis.vehiculos_total', 3)
            ->where('kpis.vehiculos_operativos', 2)
            ->where('kpis.vehiculos_mantenimiento', 1)
            ->has('alertasDocumentos')
            ->has('alertasMantenimiento')
            ->has('combustibleSerie', 6)
            ->has('flotaPorEstado')
            ->has('actividad')
        );
});

it('surfaces a document about to expire as a document alert', function (): void {
    $vehiculo = Vehiculo::factory()->create();

    VehiculoDocumento::create([
        'vehiculo_id' => $vehiculo->id,
        'tipo' => TipoDocumento::Soat,
        'fecha_vencimiento' => now()->addDays(10)->toDateString(),
    ]);

    actingAs(actorConRol('admin'))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('alertasDocumentos', 1)
            ->where('alertasDocumentos.0.tipo', 'SOAT')
            ->where('alertasDocumentos.0.estado', 'critico')
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
        ->assertInertia(fn (Assert $page) => $page->has('alertasDocumentos', 0));
});

it('scopes the dashboard to the vehicles a driver can see', function (): void {
    $user = actorConRol('conductor');
    $conductor = Conductor::factory()->create(['user_id' => $user->id]);
    Vehiculo::factory()->paraConductor($conductor)->create();
    Vehiculo::factory()->create(); // de otro conductor, no debe contar

    actingAs($user)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('esGestor', false)
            ->where('kpis.vehiculos_total', 1)
            ->where('kpis.conductores_activos', 0)
        );
});
