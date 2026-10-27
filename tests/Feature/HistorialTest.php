<?php

use App\Enums\TipoVehiculo;
use App\Models\HistorialCambio;
use App\Models\User;
use App\Models\Vehiculo;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

/**
 * El historial de movimientos: cada alta, cambio y baja de un registro, con
 * quién lo hizo y los valores de antes y después.
 */
beforeEach(function (): void {
    foreach (['admin', 'gerencia', 'contador'] as $rol) {
        Role::findOrCreate($rol, 'web');
    }
});

it('records who created, changed and deleted a record, and what changed', function (): void {
    $usuario = actorConRol('admin');
    actingAs($usuario);

    $vehiculo = Vehiculo::factory()->create(['tipo' => TipoVehiculo::Tracto, 'placa' => 'VEQ-825']);
    $vehiculo->update(['placa' => 'VEQ-826']);
    $vehiculo->delete();

    $movimientos = HistorialCambio::query()->where('modelo', 'Vehiculo')->where('modelo_id', $vehiculo->id)->orderBy('id')->get();

    expect($movimientos->pluck('accion')->all())->toBe(['creado', 'editado', 'eliminado'])
        ->and($movimientos->pluck('user_id')->unique()->all())->toBe([$usuario->id])
        ->and($movimientos[0]->etiqueta)->toBe('VEQ-825')
        ->and($movimientos[0]->cambios['placa'])->toBe([null, 'VEQ-825'])
        ->and($movimientos[1]->cambios)->toBe(['placa' => ['VEQ-825', 'VEQ-826']])
        // Al eliminar queda cómo estaba el registro.
        ->and($movimientos[2]->cambios['placa'])->toBe(['VEQ-826', null]);
});

it('masks hidden fields like passwords', function (): void {
    $usuario = User::factory()->create();
    $usuario->update(['password' => 'otra-clave-segura']);

    $cambio = HistorialCambio::query()->where('modelo', 'User')->where('accion', 'editado')->sole();

    expect($cambio->cambios['password'])->toBe(['••••', '••••']);
});

it('does not record a save that changes nothing', function (): void {
    $vehiculo = Vehiculo::factory()->create(['tipo' => TipoVehiculo::Tracto]);
    $antes = HistorialCambio::query()->count();

    $vehiculo->update(['placa' => $vehiculo->placa]);
    $vehiculo->touch();

    expect(HistorialCambio::query()->count())->toBe($antes);
});

it('shows the history to whoever may read it, filtered', function (): void {
    actingAs(actorConRol('admin'));
    Vehiculo::factory()->create(['tipo' => TipoVehiculo::Tracto, 'placa' => 'VEQ-825']);
    Vehiculo::factory()->create(['tipo' => TipoVehiculo::Tracto, 'placa' => 'BYM-861']);

    actingAs(actorConRol('gerencia'))
        ->get(route('historial.index', ['modelo' => 'Vehiculo', 'buscar' => 'VEQ']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('historial/index')
            ->has('movimientos.data', 1)
            ->where('movimientos.data.0.etiqueta', 'VEQ-825')
            ->where('movimientos.data.0.accion', 'creado'));

    actingAs(actorConRol('contador'))->get(route('historial.index'))->assertForbidden();
});
