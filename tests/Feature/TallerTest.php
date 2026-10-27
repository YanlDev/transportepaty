<?php

use App\Enums\EstadoVuelta;
use App\Enums\ResultadoTaller;
use App\Enums\TipoVehiculo;
use App\Models\Abastecimiento;
use App\Models\RevisionTaller;
use App\Models\Vehiculo;
use App\Models\Vuelta;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

/**
 * El OK de salida: taller da apto o no apto a cada ticket, y sin el apto
 * abastecimiento no puede registrar nada.
 */
beforeEach(function (): void {
    Role::findOrCreate('contador', 'web');

    $this->tracto = Vehiculo::factory()->create(['tipo' => TipoVehiculo::Tracto]);
    $this->vuelta = Vuelta::factory()->create([
        'tracto_id' => $this->tracto->id,
        'estado' => EstadoVuelta::Programada,
        'inicio' => now()->toDateString(),
    ]);
});

it('lists the tickets waiting for the workshop', function (): void {
    actingAs(actorConRol('taller'))
        ->get(route('taller.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('taller/index')
            ->where('tickets.0.id', $this->vuelta->id)
            ->where('tickets.0.revision', null)
            ->where('tickets.0.revisable', true));
});

it('asks when the unit will be ready and why when it is not fit', function (): void {
    actingAs(actorConRol('taller'))
        ->post(route('taller.store', $this->vuelta), ['resultado' => 'no_apto'])
        ->assertSessionHasErrors(['disponible_estimado', 'motivo']);

    actingAs(actorConRol('taller'))
        ->post(route('taller.store', $this->vuelta), [
            'resultado' => 'no_apto',
            'disponible_estimado' => '2026-10-28T15:30',
            'motivo' => 'Fuga de aire en la carreta',
            'trabajos' => 'Cambio de manguera',
        ])
        ->assertSessionHas('toast.type', 'warning');

    $revision = RevisionTaller::query()->sole();
    expect($revision->resultado)->toBe(ResultadoTaller::NoApto)
        ->and($revision->disponible_estimado->format('Y-m-d H:i'))->toBe('2026-10-28 15:30')
        ->and($this->vuelta->fresh()->tieneOkDeTaller())->toBeFalse();
});

it('keeps the history and the last review is the one that counts', function (): void {
    RevisionTaller::factory()->noApta()->create(['vuelta_id' => $this->vuelta->id]);

    actingAs(actorConRol('taller'))
        ->post(route('taller.store', $this->vuelta), ['resultado' => 'apto', 'trabajos' => 'Cambio de zapatas'])
        ->assertSessionHas('toast.type', 'success');

    expect($this->vuelta->fresh()->tieneOkDeTaller())->toBeTrue()
        ->and($this->vuelta->revisiones()->count())->toBe(2);

    actingAs(actorConRol('taller'))
        ->get(route('vueltas.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tablero.0.taller.resultado', 'apto')
            ->where('tablero.0.taller.trabajos', 'Cambio de zapatas')
            ->has('tablero.0.taller_anteriores', 1));
});

it('does not let fuel be registered without the workshop OK', function (): void {
    $datos = [
        'vehiculo_id' => $this->tracto->id,
        'fecha' => now()->toDateString(),
        'galones' => 100,
    ];

    actingAs(actorConRol('abastecimiento'))
        ->post(route('abastecimiento.store'), $datos)
        ->assertSessionHasErrors('vehiculo_id');

    RevisionTaller::factory()->noApta()->create(['vuelta_id' => $this->vuelta->id]);

    actingAs(actorConRol('abastecimiento'))
        ->post(route('abastecimiento.store'), $datos)
        ->assertSessionHasErrors('vehiculo_id');

    RevisionTaller::factory()->create(['vuelta_id' => $this->vuelta->id]);

    actingAs(actorConRol('abastecimiento'))
        ->post(route('abastecimiento.store'), $datos)
        ->assertSessionHas('toast.type', 'success');

    expect(Abastecimiento::query()->sole()->vuelta_id)->toBe($this->vuelta->id);
});

it('does not register fuel for a unit without an open ticket', function (): void {
    $sinTicket = Vehiculo::factory()->create(['tipo' => TipoVehiculo::Tracto]);

    actingAs(actorConRol('abastecimiento'))
        ->post(route('abastecimiento.store'), [
            'vehiculo_id' => $sinTicket->id,
            'fecha' => now()->toDateString(),
            'galones' => 100,
        ])
        ->assertSessionHasErrors('vehiculo_id');
});

it('only lets the workshop give the OK', function (): void {
    actingAs(actorConRol('abastecimiento'))
        ->post(route('taller.store', $this->vuelta), ['resultado' => 'apto'])
        ->assertForbidden();

    actingAs(actorConRol('contador'))->get(route('taller.index'))->assertForbidden();
    actingAs(actorConRol('abastecimiento'))->get(route('taller.index'))->assertOk();
});

it('does not review a closed ticket', function (): void {
    $this->vuelta->update(['estado' => EstadoVuelta::Cerrada]);

    actingAs(actorConRol('taller'))
        ->post(route('taller.store', $this->vuelta), ['resultado' => 'apto'])
        ->assertForbidden();
});
