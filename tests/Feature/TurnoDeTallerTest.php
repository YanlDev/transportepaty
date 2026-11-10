<?php

use App\Enums\EstadoOrdenTrabajo;
use App\Enums\EstadoVuelta;
use App\Enums\Permiso;
use App\Enums\TipoVehiculo;
use App\Models\LecturaKilometraje;
use App\Models\OrdenTrabajo;
use App\Models\User;
use App\Models\Vehiculo;
use App\Models\Vuelta;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

/*
 * El turno de taller: la bandeja del OK de salida dice lo que hay que saber
 * de cada unidad (orden abierta, último km), qué está en taller y a qué
 * llegada le falta el km, sin pasar por otros módulos.
 */
beforeEach(function (): void {
    $this->tracto = Vehiculo::factory()->create(['tipo' => TipoVehiculo::Tracto, 'placa' => 'CCB-888']);
    $this->vuelta = Vuelta::factory()->create([
        'tracto_id' => $this->tracto->id,
        'estado' => EstadoVuelta::Programada,
        'inicio' => now()->toDateString(),
    ]);
});

it('warns about an open work order on the unit and lists it as in the workshop', function (): void {
    $orden = OrdenTrabajo::factory()->create([
        'vehiculo_id' => $this->tracto->id,
        'estado' => EstadoOrdenTrabajo::EnProceso,
    ]);

    actingAs(actorConRol('taller'))
        ->get(route('taller.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('taller/index')
            ->where('tickets.0.vehiculo_id', $this->tracto->id)
            ->where('tickets.0.avisos.0.tipo', 'orden')
            ->where('tickets.0.avisos.0.tono', 'mal')
            ->where('tickets.0.avisos.0.texto', "{$orden->numero()} abierta")
            ->has('ordenes', 1)
            ->where('ordenes.0.numero', $orden->numero())
            ->where('ordenes.0.placa', 'CCB-888')
            ->where('puede.ver_unidad', true));
});

it('does not warn when the unit has nothing pending', function (): void {
    OrdenTrabajo::factory()->create(['vehiculo_id' => $this->tracto->id]);

    actingAs(actorConRol('taller'))
        ->get(route('taller.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tickets.0.avisos', [])
            ->where('ordenes', []));
});

it('proposes the last known odometer for the departure', function (): void {
    LecturaKilometraje::factory()->create(['vehiculo_id' => $this->tracto->id, 'kilometraje' => 182450]);

    actingAs(actorConRol('taller'))
        ->get(route('taller.index'))
        ->assertInertia(fn (Assert $page) => $page->where('tickets.0.km_actual', 182450));
});

it('asks for the odometer of a unit that arrived and has no reading since', function (): void {
    $llegada = Vuelta::factory()->create(['llegada_el' => now()->subDay()->toDateString()]);
    $conKm = Vuelta::factory()->create(['llegada_el' => now()->subDay()->toDateString()]);
    LecturaKilometraje::factory()->create(['vehiculo_id' => $conKm->tracto_id, 'leido_en' => now()]);
    Vuelta::factory()->create(['llegada_el' => now()->subDays(20)->toDateString()]);

    actingAs(actorConRol('taller'))
        ->get(route('taller.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('llegadas', 1)
            ->where('llegadas.0.id', $llegada->tracto_id)
            ->where('puede.registrar_km', true));
});

it('hides maintenance details from someone who only sees the departure tray', function (): void {
    OrdenTrabajo::factory()->create(['vehiculo_id' => $this->tracto->id, 'estado' => EstadoOrdenTrabajo::Pendiente]);
    Vuelta::factory()->create(['llegada_el' => now()->subDay()->toDateString()]);

    $rol = Role::findOrCreate('solo-mira-taller', 'web');
    $rol->syncPermissions([Permiso::TallerVer->value]);

    actingAs(User::factory()->create()->assignRole($rol))
        ->get(route('taller.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tickets.0.avisos', [])
            ->where('tickets.0.km_actual', null)
            ->where('ordenes', [])
            ->where('llegadas', [])
            ->where('puede.ver_unidad', false)
            ->where('puede.registrar_km', false));
});

it('gives the workshop role the warehouse counter', function (): void {
    $taller = actorConRol('taller');

    expect($taller->can(Permiso::AlmacenDespachar->value))->toBeTrue()
        ->and($taller->can(Permiso::AlmacenIngresar->value))->toBeTrue()
        ->and($taller->can(Permiso::AlmacenInventariar->value))->toBeTrue()
        ->and($taller->can(Permiso::AlmacenCatalogo->value))->toBeTrue()
        ->and($taller->can(Permiso::AlmacenVerCostos->value))->toBeFalse()
        ->and($taller->can(Permiso::AlmacenAnular->value))->toBeFalse();
});

it('shows the open ticket and the tyres on the unit sheet', function (): void {
    actingAs(actorConRol('taller'))
        ->get(route('mantenimiento.show', $this->tracto))
        ->assertInertia(fn (Assert $page) => $page
            ->component('mantenimiento/show')
            ->where('ticket.id', $this->vuelta->id)
            ->where('ticket.revisable', true)
            ->where('ticket.revision', null)
            ->has('llantas', 10));
});

it('shows no ticket on the sheet of a unit that is not scheduled', function (): void {
    $libre = Vehiculo::factory()->create(['tipo' => TipoVehiculo::Tracto]);

    actingAs(actorConRol('taller'))
        ->get(route('mantenimiento.show', $libre))
        ->assertInertia(fn (Assert $page) => $page->where('ticket', null));
});

it('opens maintenance on the work queue: open orders and units without odometer', function (): void {
    $orden = OrdenTrabajo::factory()->create([
        'vehiculo_id' => $this->tracto->id,
        'estado' => EstadoOrdenTrabajo::Pendiente,
    ]);

    actingAs(actorConRol('taller'))
        ->get(route('mantenimiento.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('mantenimiento/index')
            ->where('ordenes.0.numero', $orden->numero())
            ->where('vencidos.total', 0)
            ->where('proximos.total', 0)
            ->where('llantas.total', 0)
            ->has('equipamiento.unidades')
            ->has('sin_km.unidades'));
});
