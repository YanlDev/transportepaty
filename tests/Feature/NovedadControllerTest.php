<?php

use App\Enums\TipoNovedad;
use App\Enums\TipoVehiculo;
use App\Models\Novedad;
use App\Models\Vehiculo;
use App\Services\RelojOperativo;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    foreach (['admin', 'visor', 'conductor', 'contador'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

it('registra una novedad sobre un tracto', function (): void {
    $tracto = Vehiculo::factory()->create(['tipo' => TipoVehiculo::Tracto]);

    actingAs(actorConRol('admin'))
        ->post(route('novedades.store'), [
            'tracto_id' => $tracto->id,
            'tipo' => TipoNovedad::NoHabido->value,
            'desde' => '2026-09-01',
            'motivo' => 'No se presentó a la salida.',
        ])
        ->assertSessionHasNoErrors();

    $novedad = Novedad::query()->sole();

    expect($novedad->tracto_id)->toBe($tracto->id)
        ->and($novedad->tipo)->toBe(TipoNovedad::NoHabido)
        ->and($novedad->hasta)->toBeNull();
});

/**
 * La novedad es del fierro, no del chofer: el formulario solo ofrece tractos y
 * la validación lo sostiene.
 */
it('rechaza una novedad sobre algo que no es un tracto', function (): void {
    $carreta = Vehiculo::factory()->create(['tipo' => TipoVehiculo::Carreta]);

    actingAs(actorConRol('admin'))
        ->post(route('novedades.store'), [
            'tracto_id' => $carreta->id,
            'tipo' => TipoNovedad::NoHabido->value,
            'desde' => '2026-09-01',
        ])
        ->assertSessionHasErrors('tracto_id');

    expect(Novedad::query()->count())->toBe(0);
});

it('deja fuera de registrar novedades a quien no administra', function (): void {
    $tracto = Vehiculo::factory()->create(['tipo' => TipoVehiculo::Tracto]);

    foreach (['visor', 'conductor', 'contador'] as $rol) {
        actingAs(actorConRol($rol))
            ->post(route('novedades.store'), [
                'tracto_id' => $tracto->id,
                'tipo' => TipoNovedad::NoHabido->value,
                'desde' => '2026-09-01',
            ])
            ->assertForbidden();
    }

    expect(Novedad::query()->count())->toBe(0);
});

/**
 * Levantar no borra: el rastro de por qué una unidad no subió tal día tiene que
 * quedar, así que se le pone fecha de cierre y sigue ahí.
 */
it('levanta la novedad con la fecha de hoy sin borrarla', function (): void {
    $novedad = Novedad::factory()->create();

    actingAs(actorConRol('admin'))
        ->post(route('novedades.levantar', $novedad))
        ->assertSessionHasNoErrors();

    expect(Novedad::query()->count())->toBe(1)
        ->and($novedad->fresh()->hasta->toDateString())->toBe(RelojOperativo::hoy());
});

it('deja fuera de levantar novedades a quien no administra', function (): void {
    $novedad = Novedad::factory()->create();

    foreach (['visor', 'conductor', 'contador'] as $rol) {
        actingAs(actorConRol($rol))
            ->post(route('novedades.levantar', $novedad))
            ->assertForbidden();
    }

    expect($novedad->fresh()->hasta)->toBeNull();
});

/**
 * Una novedad levantada deja de pesar: es lo que separa las unidades que hoy
 * no se pueden programar de las que ya volvieron.
 */
it('saca de las vigentes a la novedad ya levantada', function (): void {
    Novedad::factory()->create();
    Novedad::factory()->levantada('2026-09-01')->create();

    expect(Novedad::vigentes()->count())->toBe(1);
});
