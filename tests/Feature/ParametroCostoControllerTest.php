<?php

use App\Models\ComponenteCosto;
use App\Models\ParametroFlota;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    foreach (['admin', 'visor', 'conductor', 'contador'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

/**
 * Los parámetros de flota vigentes, tal como los crea el seeder de la
 * aplicación. Se piden por `vigentes()` para no inventar una segunda fila.
 *
 * @param  array<string, mixed>  $extra
 */
function parametrosFlota(array $extra = []): array
{
    $flota = ParametroFlota::vigentes();

    return [
        'tamano_flota' => $flota->tamano_flota,
        'dias_ano' => $flota->dias_ano,
        'dias_mantenimiento' => $flota->dias_mantenimiento,
        'dias_certificaciones' => $flota->dias_certificaciones,
        'dias_sincronizacion' => $flota->dias_sincronizacion,
        'igv_pct' => $flota->igv_pct,
        'margen_pct_default' => $flota->margen_pct_default,
        'viatico_dia' => $flota->viatico_dia,
        ...$extra,
    ];
}

it('muestra la estructura de costos al admin', function (): void {
    actingAs(actorConRol('admin'))
        ->get(route('parametros-costo.edit'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('parametros-costo/edit')
            ->has('flota.dias_disponibles')
            ->has('componentes')
            ->has('totales'));
});

/**
 * La pantalla es el editor mismo: no hay una vista de solo lectura de los
 * costos, así que quien no los cambia tampoco los abre.
 */
it('deja la estructura de costos fuera del alcance de los demás roles', function (): void {
    foreach (['visor', 'conductor', 'contador'] as $rol) {
        actingAs(actorConRol($rol))
            ->get(route('parametros-costo.edit'))
            ->assertForbidden();
    }
});

it('actualiza los parámetros de flota', function (): void {
    actingAs(actorConRol('admin'))
        ->put(route('parametros-costo.update'), parametrosFlota(['viatico_dia' => 95.5]))
        ->assertSessionHasNoErrors();

    expect((float) ParametroFlota::vigentes()->viatico_dia)->toBe(95.5);
});

/**
 * Si los días perdidos se comieran el año entero no quedarían días sobre los
 * que repartir los costos fijos, y toda la estructura se iría a cero.
 */
it('no deja que los días perdidos cubran todo el año', function (): void {
    actingAs(actorConRol('admin'))
        ->put(route('parametros-costo.update'), parametrosFlota([
            'dias_ano' => 365,
            'dias_mantenimiento' => 200,
            'dias_certificaciones' => 100,
            'dias_sincronizacion' => 100,
        ]))
        ->assertSessionHasErrors('dias_sincronizacion');
});

it('deja fuera de actualizar los parámetros a quien no administra', function (): void {
    foreach (['visor', 'conductor', 'contador'] as $rol) {
        actingAs(actorConRol($rol))
            ->put(route('parametros-costo.update'), parametrosFlota(['viatico_dia' => 1]))
            ->assertForbidden();
    }
});

it('actualiza un componente de costo', function (): void {
    $componente = ComponenteCosto::query()->ordenados()->firstOrFail();

    actingAs(actorConRol('admin'))
        ->put(route('parametros-costo.componentes.update', $componente), [
            'nombre' => $componente->nombre,
            'naturaleza' => $componente->naturaleza->value,
            'activo' => false,
            'entradas' => $componente->entradas,
        ])
        ->assertSessionHasNoErrors();

    expect($componente->fresh()->activo)->toBeFalse();
});

it('deja fuera de actualizar un componente a quien no administra', function (): void {
    $componente = ComponenteCosto::query()->ordenados()->firstOrFail();

    foreach (['visor', 'conductor', 'contador'] as $rol) {
        actingAs(actorConRol($rol))
            ->put(route('parametros-costo.componentes.update', $componente), [
                'nombre' => 'Cambiado',
                'naturaleza' => $componente->naturaleza->value,
                'activo' => false,
                'entradas' => $componente->entradas,
            ])
            ->assertForbidden();
    }

    expect($componente->fresh()->activo)->toBeTrue();
});
