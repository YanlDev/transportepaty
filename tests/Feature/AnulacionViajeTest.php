<?php

use App\Models\Conductor;
use App\Models\Factura;
use App\Models\Viaje;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    foreach (['admin', 'visor', 'contador'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

it('lets an admin mark a GR as anulada with a reason', function (): void {
    $admin = actorConRol('admin');
    $viaje = Viaje::factory()->create();

    actingAs($admin)
        ->post(route('viajes.anular', $viaje), ['motivo' => 'Placa del tracto mal escrita'])
        ->assertRedirect()
        ->assertSessionHas('toast.type', 'success');

    $viaje = Viaje::query()->conAnuladas()->findOrFail($viaje->id);

    expect($viaje->estaAnulada())->toBeTrue()
        ->and($viaje->anulada_por)->toBe($admin->id)
        ->and($viaje->motivo_anulacion)->toBe('Placa del tracto mal escrita');
});

it('keeps the anulada in the viajes list, flagged and outside its trip group', function (): void {
    $buena = Viaje::factory()->create(['fecha_traslado' => '2026-09-23']);
    $anulada = Viaje::factory()->create([
        'fecha_traslado' => '2026-09-23',
        'tracto_id' => $buena->tracto_id,
        'carreta_id' => $buena->carreta_id,
        'conductor_id' => $buena->conductor_id,
        'placa_tracto' => $buena->placa_tracto,
        'placa_carreta' => $buena->placa_carreta,
    ]);

    actingAs(actorConRol('admin'))->post(route('viajes.anular', $anulada), ['motivo' => 'Reemitida']);

    actingAs(actorConRol('visor'))
        ->get(route('viajes.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('viajes.data', 2)
            ->where('viajes.data', fn ($filas): bool => collect($filas)->firstWhere('id', $anulada->id)['anulacion']['motivo'] === 'Reemitida'
                && collect($filas)->firstWhere('id', $buena->id)['anulacion'] === null
                && collect($filas)->firstWhere('id', $anulada->id)['grupo_viaje'] !== collect($filas)->firstWhere('id', $buena->id)['grupo_viaje'])
        );
});

it('forbids a visor from anulando a GR', function (): void {
    $viaje = Viaje::factory()->create();

    actingAs(actorConRol('visor'))
        ->post(route('viajes.anular', $viaje))
        ->assertForbidden();

    expect($viaje->fresh()->estaAnulada())->toBeFalse();
});

it('refuses to anular a GR that is already in a factura', function (): void {
    $viaje = Viaje::factory()->create(['factura_id' => Factura::factory()->create()->id]);

    actingAs(actorConRol('admin'))
        ->post(route('viajes.anular', $viaje))
        ->assertSessionHasErrors('motivo');

    expect($viaje->fresh()->estaAnulada())->toBeFalse();
});

it('reactivates an anulada so it counts again', function (): void {
    $viaje = Viaje::factory()->create();
    $admin = actorConRol('admin');

    actingAs($admin)->post(route('viajes.anular', $viaje), ['motivo' => 'Error']);
    actingAs($admin)->delete(route('viajes.reactivar', $viaje))->assertRedirect();

    $viaje = Viaje::query()->findOrFail($viaje->id);

    expect($viaje->estaAnulada())->toBeFalse()
        ->and($viaje->anulada_por)->toBeNull()
        ->and($viaje->motivo_anulacion)->toBeNull();
});

it('leaves the anulada out of the tablero, the cobranza and the conductor ficha', function (): void {
    $conductor = Conductor::factory()->create();
    Viaje::factory()->deMinsur()->create(['fecha_traslado' => now()->toDateString(), 'conductor_id' => $conductor->id]);
    $anulada = Viaje::factory()->create([
        'cliente' => 'CRISAR LOGISTICA S.A.C.',
        'fecha_traslado' => now()->toDateString(),
        'conductor_id' => $conductor->id,
    ]);

    $admin = actorConRol('admin');
    actingAs($admin)->post(route('viajes.anular', $anulada));

    actingAs($admin)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('viajesPorTipoCliente.minsur', 1)
            ->where('viajesPorTipoCliente.particulares', 0)
        );

    actingAs(actorConRol('contador'))
        ->get(route('contabilidad.index'))
        ->assertInertia(fn (Assert $page) => $page->has('viajes.data', 1));

    actingAs($admin)
        ->get(route('conductores.show', $conductor))
        ->assertInertia(fn (Assert $page) => $page
            ->where('estadisticas.viajes_totales', 1)
            ->has('viajes', 1)
        );
});
