<?php

use App\Enums\Permiso;
use App\Models\Articulo;
use App\Models\Conductor;
use App\Models\OrdenTrabajo;
use App\Models\User;
use App\Models\Vehiculo;
use App\Models\Vuelta;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

/*
 * El buscador de toda la app (Ctrl+K): encuentra por placa, ticket, OT o
 * artículo, y solo dentro de lo que el rol puede ver.
 */

/** Un usuario con un rol armado con esos permisos. */
function usuarioQuePuede(Permiso ...$permisos): User
{
    $rol = Role::findOrCreate('rol-de-prueba-'.count($permisos).'-'.$permisos[0]->value, 'web');
    $rol->syncPermissions(array_map(fn (Permiso $permiso): string => $permiso->value, $permisos));

    return User::factory()->create()->assignRole($rol);
}

/** Los títulos de los grupos que devolvió la búsqueda. */
function gruposDe(string $texto): array
{
    return getJson(route('buscar', ['q' => $texto]))
        ->assertSuccessful()
        ->json('grupos.*.titulo');
}

it('requires a session', function (): void {
    get(route('buscar', ['q' => 'VBI']))->assertRedirect(route('login'));
});

it('finds a unit by its plate, with or without a dash', function (): void {
    $unidad = Vehiculo::factory()->create(['placa' => 'VBI701']);
    actingAs(usuarioQuePuede(Permiso::VehiculosVer));

    getJson(route('buscar', ['q' => 'vbi-7']))
        ->assertSuccessful()
        ->assertJsonPath('grupos.0.titulo', 'Unidades')
        ->assertJsonPath('grupos.0.items.0.titulo', 'VBI701')
        ->assertJsonPath('grupos.0.items.0.url', route('vehiculos.show', $unidad, absolute: false));
});

it('finds a plate stored with a dash when it is typed without it', function (): void {
    Vehiculo::factory()->create(['placa' => 'CCB-888']);
    actingAs(usuarioQuePuede(Permiso::VehiculosVer));

    expect(gruposDe('ccb888'))->toBe(['Unidades'])
        ->and(gruposDe('CCB 88'))->toBe(['Unidades']);
});

it('returns nothing for a single letter', function (): void {
    Vehiculo::factory()->create(['placa' => 'VBI701']);
    actingAs(usuarioQuePuede(Permiso::VehiculosVer));

    expect(gruposDe('V'))->toBe([]);
});

it('only searches the modules the role can see', function (): void {
    $unidad = Vehiculo::factory()->create(['placa' => 'CCB888']);
    Vuelta::factory()->create(['tracto_id' => $unidad->id]);
    OrdenTrabajo::factory()->create(['vehiculo_id' => $unidad->id]);

    actingAs(usuarioQuePuede(Permiso::MantenimientoVer));
    expect(gruposDe('ccb-888'))->toBe(['Órdenes de trabajo']);

    actingAs(usuarioQuePuede(Permiso::VehiculosVer, Permiso::ProgramacionVer));
    expect(gruposDe('CCB888'))->toBe(['Unidades', 'Tickets']);
});

it('finds a work order by its number', function (): void {
    $orden = OrdenTrabajo::factory()->create();
    actingAs(usuarioQuePuede(Permiso::MantenimientoVer));

    getJson(route('buscar', ['q' => $orden->numero()]))
        ->assertSuccessful()
        ->assertJsonPath('grupos.0.items.0.url', route('mantenimiento.ordenes.edit', $orden, absolute: false));
});

it('finds articles by name or part number and drivers by name', function (): void {
    Articulo::factory()->create(['nombre' => 'FILTRO DE ACEITE', 'numero_parte' => 'LF9009']);
    Conductor::factory()->create(['nombres' => 'Serafin', 'apellidos' => 'Pacheco Cutipa']);
    actingAs(usuarioQuePuede(Permiso::AlmacenVer, Permiso::ConductoresVer));

    expect(gruposDe('lf90'))->toBe(['Artículos'])
        ->and(gruposDe('filtro'))->toBe(['Artículos'])
        ->and(gruposDe('pacheco'))->toBe(['Conductores']);
});
