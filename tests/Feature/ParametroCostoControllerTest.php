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
        ->put(route('parametros-costo.update'), parametrosFlota(['margen_pct_default' => 0.15]))
        ->assertSessionHasNoErrors();

    expect(ParametroFlota::vigentes()->margen_pct_default)->toBe(0.15);
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
            ->put(route('parametros-costo.update'), parametrosFlota(['margen_pct_default' => 0.5]))
            ->assertForbidden();
    }
});

it('actualiza un componente de costo', function (): void {
    $componente = ComponenteCosto::query()->ordenados()->firstOrFail();

    actingAs(actorConRol('admin'))
        ->put(route('parametros-costo.componentes.update', $componente), [
            'nombre' => $componente->nombre,
            'tasa' => 270.5,
            'activo' => false,
            'entradas' => $componente->entradas,
        ])
        ->assertSessionHasNoErrors();

    $componente->refresh();

    expect($componente->activo)->toBeFalse()
        ->and($componente->tasa)->toBe(270.5);
});

/**
 * Las líneas sin calculadora no tienen entradas que mandar: con el nombre y la
 * tasa alcanza.
 */
it('actualiza la tasa de una línea sin calculadora', function (): void {
    $componente = ComponenteCosto::query()
        ->where('nombre', 'Otros gastos variables (peajes, viáticos y otros)')
        ->sole();

    actingAs(actorConRol('admin'))
        ->put(route('parametros-costo.componentes.update', $componente), [
            'nombre' => $componente->nombre,
            'tasa' => 0.5843,
            'activo' => true,
        ])
        ->assertSessionHasNoErrors();

    expect($componente->fresh()->tasa)->toBe(0.5843);
});

it('agrega una línea nueva al tarifario', function (): void {
    actingAs(actorConRol('admin'))
        ->post(route('parametros-costo.componentes.store'), [
            'nombre' => 'Escolta',
            'tipo' => 'fijo_dia',
            'tasa' => 150,
        ])
        ->assertSessionHasNoErrors();

    $componente = ComponenteCosto::query()->where('nombre', 'Escolta')->sole();

    expect($componente->tasa)->toBe(150.0)
        ->and($componente->activo)->toBeTrue()
        ->and($componente->tieneCalculadora())->toBeFalse()
        ->and($componente->orden)->toBe((int) ComponenteCosto::query()->max('orden'));
});

it('deja fuera de actualizar un componente a quien no administra', function (): void {
    $componente = ComponenteCosto::query()->ordenados()->firstOrFail();

    foreach (['visor', 'conductor', 'contador'] as $rol) {
        actingAs(actorConRol($rol))
            ->put(route('parametros-costo.componentes.update', $componente), [
                'nombre' => 'Cambiado',
                'tasa' => 1,
                'activo' => false,
            ])
            ->assertForbidden();

        actingAs(actorConRol($rol))
            ->post(route('parametros-costo.componentes.store'), [
                'nombre' => 'Escolta',
                'tipo' => 'fijo_dia',
                'tasa' => 150,
            ])
            ->assertForbidden();
    }

    expect($componente->fresh()->activo)->toBeTrue()
        ->and(ComponenteCosto::query()->where('nombre', 'Escolta')->exists())->toBeFalse();
});
