<?php

use App\Enums\ResultadoActivacion;
use App\Models\Activacion;
use App\Models\Conductor;
use App\Models\User;
use App\Models\Vehiculo;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    foreach (['admin', 'visor', 'conductor'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

/**
 * Creates a driver user linked to a vehicle assigned to them.
 *
 * @return array{0: User, 1: Vehiculo}
 */
function conductorConUnidad(): array
{
    $user = actorConRol('conductor');
    $conductor = Conductor::factory()->create(['user_id' => $user->id]);
    $vehiculo = Vehiculo::factory()->paraConductor($conductor)->create();

    return [$user, $vehiculo];
}

it('redirects guests to login', function (): void {
    $vehiculo = Vehiculo::factory()->create();

    $this->get(route('vehiculos.activaciones.index', $vehiculo))
        ->assertRedirect(route('login'));
});

it('shows the activations page with history to an admin', function (): void {
    $vehiculo = Vehiculo::factory()->create();
    Activacion::factory()->for($vehiculo)->count(3)->create();

    actingAs(actorConRol('admin'))
        ->get(route('vehiculos.activaciones.index', $vehiculo))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('vehiculos/activaciones')
            ->has('activaciones', 3)
            ->has('resultados')
            ->where('reposoDias', config('flota.reposo_dias'))
            ->where('puede.registrar', true)
            ->where('puede.gestionar', true)
        );
});

it('lets a driver view only their assigned vehicle', function (): void {
    [$user, $vehiculo] = conductorConUnidad();

    actingAs($user)
        ->get(route('vehiculos.activaciones.index', $vehiculo))
        ->assertSuccessful();
});

it('forbids a driver from another vehicle', function (): void {
    [$user] = conductorConUnidad();
    $ajeno = Vehiculo::factory()->create();

    actingAs($user)
        ->get(route('vehiculos.activaciones.index', $ajeno))
        ->assertForbidden();
});

it('lets a driver register an activation on their vehicle', function (): void {
    [$user, $vehiculo] = conductorConUnidad();

    actingAs($user)
        ->post(route('vehiculos.activaciones.store', $vehiculo), [
            'fecha' => '2026-07-01',
            'kilometraje' => 12500,
            'resultado' => ResultadoActivacion::SinNovedad->value,
        ])
        ->assertRedirect();

    $activacion = Activacion::firstOrFail();

    expect($activacion->vehiculo_id)->toBe($vehiculo->id)
        ->and($activacion->conductor_id)->toBe($vehiculo->conductor_id)
        ->and($activacion->registrada_por)->toBe($user->id)
        ->and($activacion->resultado)->toBe(ResultadoActivacion::SinNovedad)
        ->and($activacion->kilometraje)->toBe(12500);
});

it('forbids a driver from registering on a vehicle that is not theirs', function (): void {
    [$user] = conductorConUnidad();
    $ajeno = Vehiculo::factory()->create();

    actingAs($user)
        ->post(route('vehiculos.activaciones.store', $ajeno), [
            'resultado' => ResultadoActivacion::SinNovedad->value,
        ])
        ->assertForbidden();
});

it('requires a description when an anomaly is reported', function (): void {
    $vehiculo = Vehiculo::factory()->create();

    actingAs(actorConRol('admin'))
        ->post(route('vehiculos.activaciones.store', $vehiculo), [
            'resultado' => ResultadoActivacion::Anomalia->value,
        ])
        ->assertSessionHasErrors('observaciones');
});

it('updates a GPS-less vehicle odometer when the activation reads higher', function (): void {
    $vehiculo = Vehiculo::factory()->create(['imei' => null, 'kilometraje' => 10000]);

    actingAs(actorConRol('admin'))
        ->post(route('vehiculos.activaciones.store', $vehiculo), [
            'kilometraje' => 10120,
            'resultado' => ResultadoActivacion::SinNovedad->value,
        ])
        ->assertRedirect();

    expect((int) $vehiculo->fresh()->kilometraje)->toBe(10120);
});

it('does not touch a GPS vehicle odometer from an activation', function (): void {
    $vehiculo = Vehiculo::factory()->create(['imei' => '860112070376688', 'kilometraje' => 10000]);

    actingAs(actorConRol('admin'))
        ->post(route('vehiculos.activaciones.store', $vehiculo), [
            'kilometraje' => 99000,
            'resultado' => ResultadoActivacion::SinNovedad->value,
        ])
        ->assertRedirect();

    expect((int) $vehiculo->fresh()->kilometraje)->toBe(10000);
});

it('returns 404 when the activation does not belong to the vehicle', function (): void {
    $vehiculo = Vehiculo::factory()->create();
    $otro = Vehiculo::factory()->create();
    $activacion = Activacion::factory()->for($otro)->create();

    actingAs(actorConRol('admin'))
        ->delete(route('vehiculos.activaciones.destroy', [$vehiculo, $activacion]))
        ->assertNotFound();
});

it('lets an admin delete an activation but forbids a driver', function (): void {
    [$user, $vehiculo] = conductorConUnidad();
    $activacion = Activacion::factory()->for($vehiculo)->create();

    actingAs($user)
        ->delete(route('vehiculos.activaciones.destroy', [$vehiculo, $activacion]))
        ->assertForbidden();

    actingAs(actorConRol('admin'))
        ->delete(route('vehiculos.activaciones.destroy', [$vehiculo, $activacion]))
        ->assertRedirect();

    $this->assertDatabaseMissing('activaciones', ['id' => $activacion->id]);
});
