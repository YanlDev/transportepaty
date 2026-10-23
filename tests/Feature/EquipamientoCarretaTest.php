<?php

use App\Enums\EstadoEquipamiento;
use App\Enums\Permiso;
use App\Models\ConteoEquipamiento;
use App\Models\ItemEquipamiento;
use App\Models\User;
use App\Models\Vehiculo;
use App\Services\EstadoEquipamientoCarretas;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

/** Quien cuenta en base: ve mantenimiento y cuenta el equipamiento. */
function contador(): User
{
    $rol = Role::findOrCreate('base', 'web');
    $rol->syncPermissions([Permiso::MantenimientoVer->value, Permiso::MantenimientoEquipamiento->value]);

    return User::factory()->create()->assignRole($rol);
}

function faja(string $nombre): ItemEquipamiento
{
    return ItemEquipamiento::query()->where('nombre', $nombre)->sole();
}

/**
 * @param  array<string, array{0: int, 1: int}>  $cantidades  buenas y dañadas por nombre de ítem
 */
function contar(Vehiculo $carreta, array $cantidades, string $fecha = '2026-10-15'): void
{
    actingAs(contador())
        ->post(route('mantenimiento.equipamiento.store', $carreta), [
            'fecha' => $fecha,
            'items' => collect($cantidades)
                ->map(fn (array $cantidad, string $nombre): array => ['id' => faja($nombre)->id, 'buenas' => $cantidad[0], 'danadas' => $cantidad[1]])
                ->values()
                ->all(),
        ])
        ->assertSessionHasNoErrors();
}

it('loads the straps of 4" and 2", with and without chicharra, without a minimum yet', function (): void {
    expect(ItemEquipamiento::query()->vigentes()->pluck('minimo', 'nombre')->all())->toBe([
        'Faja de 4" con chicharra' => null,
        'Faja de 4" sin chicharra' => null,
        'Faja de 2" con chicharra' => null,
        'Faja de 2" sin chicharra' => null,
    ]);
});

it('compares the last count with the minimum, without counting the damaged ones', function (): void {
    faja('Faja de 4" con chicharra')->update(['minimo' => 8]);
    faja('Faja de 2" con chicharra')->update(['minimo' => 4]);

    $carreta = Vehiculo::factory()->carreta()->create();
    $nunca = Vehiculo::factory()->carreta()->create();

    contar($carreta, ['Faja de 4" con chicharra' => [8, 0], 'Faja de 2" con chicharra' => [4, 0]], '2026-10-01');
    contar($carreta, ['Faja de 4" con chicharra' => [6, 2], 'Faja de 2" con chicharra' => [4, 1]], '2026-10-15');

    $estados = app(EstadoEquipamientoCarretas::class)->paraFlota(collect([$carreta, $nunca]));
    $items = collect($estados[$carreta->id]['items'])->keyBy('nombre');

    expect($estados[$carreta->id]['estado'])->toBe(EstadoEquipamiento::Falta)
        ->and($estados[$carreta->id]['faltan'])->toBe(2)
        ->and($items['Faja de 4" con chicharra'])->toMatchArray(['buenas' => 6, 'danadas' => 2, 'faltan' => 2])
        ->and($items['Faja de 4" sin chicharra'])->toMatchArray(['buenas' => 0, 'faltan' => 0])
        ->and($estados[$nunca->id]['estado'])->toBe(EstadoEquipamiento::SinContar);

    // Borrar el último conteo la deja con el anterior, que estaba completo.
    actingAs(contador())
        ->delete(route('mantenimiento.equipamiento.destroy', ConteoEquipamiento::query()->latest('fecha')->first()))
        ->assertSessionHasNoErrors();

    expect(app(EstadoEquipamientoCarretas::class)->paraVehiculo($carreta)['estado'])->toBe(EstadoEquipamiento::Completo);
});

it('says counted, not complete, while no item has a minimum', function (): void {
    $carreta = Vehiculo::factory()->carreta()->create();
    contar($carreta, ['Faja de 4" con chicharra' => [3, 0]]);

    expect(app(EstadoEquipamientoCarretas::class)->paraVehiculo($carreta)['estado'])->toBe(EstadoEquipamiento::Contado);
});

it('counts only carretas', function (): void {
    actingAs(contador())
        ->post(route('mantenimiento.equipamiento.store', Vehiculo::factory()->create()), [
            'fecha' => '2026-10-15',
            'items' => [['id' => faja('Faja de 4" con chicharra')->id, 'buenas' => 1, 'danadas' => 0]],
        ])
        ->assertSessionHasErrors('items');

    actingAs(contador())->get(route('mantenimiento.equipamiento.show', Vehiculo::factory()->create()))->assertNotFound();
});

it('lists the carretas missing something first, and shows each one with its counts', function (): void {
    faja('Faja de 4" con chicharra')->update(['minimo' => 8]);

    $completa = Vehiculo::factory()->carreta()->create(['placa' => 'AAA-111']);
    contar($completa, ['Faja de 4" con chicharra' => [8, 0]]);
    $incompleta = Vehiculo::factory()->carreta()->create(['placa' => 'ZZZ-999']);
    contar($incompleta, ['Faja de 4" con chicharra' => [5, 0]]);
    Vehiculo::factory()->create();

    actingAs(contador())
        ->get(route('mantenimiento.equipamiento.index'))
        ->assertInertia(fn ($page) => $page
            ->component('mantenimiento/equipamiento/index')
            ->has('carretas', 2)
            ->where('carretas.0.id', $incompleta->id)
            ->where('carretas.0.faltan', 3)
            ->where('carretas.1.estado', 'completo')
            ->has('catalogo', 4)
        );

    actingAs(contador())
        ->get(route('mantenimiento.equipamiento.show', $incompleta))
        ->assertInertia(fn ($page) => $page
            ->component('mantenimiento/equipamiento/show')
            ->where('carreta.estado', 'falta')
            ->has('carreta.items', 4)
            ->has('conteos', 1)
            ->has('conteos.0.detalles', 1)
        );
});

it('lets whoever configures the plans edit the items and their minimums', function (): void {
    $catalogo = ItemEquipamiento::query()->vigentes()->get();
    $datos = ['items' => [
        ['id' => $catalogo[0]->id, 'nombre' => 'Faja de 4" con chicharra', 'minimo' => 10],
        ['id' => $catalogo[2]->id, 'nombre' => 'Faja de 2" con chicharra', 'minimo' => 4],
        ['id' => null, 'nombre' => 'Cadena de amarre', 'minimo' => 2],
    ]];

    actingAs(contador())->put(route('mantenimiento.equipamiento.catalogo'), $datos)->assertForbidden();

    $rol = Role::findOrCreate('jefe_taller', 'web');
    $rol->syncPermissions([Permiso::MantenimientoVer->value, Permiso::MantenimientoConfigurar->value]);

    actingAs(User::factory()->create()->assignRole($rol))
        ->put(route('mantenimiento.equipamiento.catalogo'), $datos)
        ->assertSessionHasNoErrors();

    // Los quitados dejan de contarse, pero siguen en la base.
    expect(ItemEquipamiento::query()->vigentes()->pluck('minimo', 'nombre')->all())->toBe([
        'Faja de 4" con chicharra' => 10,
        'Faja de 2" con chicharra' => 4,
        'Cadena de amarre' => 2,
    ])->and(ItemEquipamiento::query()->count())->toBe(5);
});

it('keeps counting for whoever has its permission', function (): void {
    $rol = Role::findOrCreate('mirador', 'web');
    $rol->syncPermissions([Permiso::MantenimientoVer->value]);
    $mirador = User::factory()->create()->assignRole($rol);
    $carreta = Vehiculo::factory()->carreta()->create();

    actingAs($mirador)->get(route('mantenimiento.equipamiento.show', $carreta))->assertSuccessful();
    actingAs($mirador)
        ->post(route('mantenimiento.equipamiento.store', $carreta), ['fecha' => '2026-10-15', 'items' => [['id' => faja('Faja de 4" con chicharra')->id, 'buenas' => 1, 'danadas' => 0]]])
        ->assertForbidden();
    actingAs(User::factory()->create())->get(route('mantenimiento.equipamiento.index'))->assertForbidden();
});
