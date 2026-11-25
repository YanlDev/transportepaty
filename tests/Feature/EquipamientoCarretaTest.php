<?php

use App\Enums\EstadoEquipamiento;
use App\Enums\Permiso;
use App\Models\ConteoEquipamiento;
use App\Models\ItemEquipamiento;
use App\Models\MovimientoEquipamiento;
use App\Models\PiezaEquipamiento;
use App\Models\User;
use App\Models\Vehiculo;
use App\Models\VencimientoEquipamiento;
use App\Services\EquipamientoDeUnidades;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
    expect(ItemEquipamiento::query()->vigentes()->where('aplica_a', 'carreta')->pluck('minimo', 'nombre')->all())->toBe([
        'Faja de 4" con chicharra' => null,
        'Faja de 4" sin chicharra' => null,
        'Faja de 2" con chicharra' => null,
        'Faja de 2" sin chicharra' => null,
    ])->and(ItemEquipamiento::query()->vigentes()->where('aplica_a', 'tracto')->pluck('lleva_vencimiento', 'nombre')->all())->toBe([
        'Extintor' => true,
        'Botiquín' => true,
        'Conos de seguridad' => false,
        'Kit antiderrame' => false,
        'Pico' => false,
        'Pala' => false,
        'Caja de herramientas' => false,
        'Triángulos de seguridad' => false,
        'Tacos de seguridad' => false,
        'Gata' => false,
        'Llave de ruedas' => false,
    ]);
});

it('loads the basic kit of a tractor with how many it must carry', function (): void {
    expect(ItemEquipamiento::query()->vigentes()->where('aplica_a', 'tracto')->pluck('minimo', 'nombre')->all())->toBe([
        'Extintor' => 1,
        'Botiquín' => 1,
        'Conos de seguridad' => 2,
        'Kit antiderrame' => 1,
        'Pico' => 1,
        'Pala' => 1,
        'Caja de herramientas' => 1,
        'Triángulos de seguridad' => 2,
        'Tacos de seguridad' => 2,
        'Gata' => 1,
        'Llave de ruedas' => 1,
    ]);
});

it('compares the last count with the minimum, without counting the damaged ones', function (): void {
    faja('Faja de 4" con chicharra')->update(['minimo' => 8]);
    faja('Faja de 2" con chicharra')->update(['minimo' => 4]);

    $carreta = Vehiculo::factory()->carreta()->create();
    $nunca = Vehiculo::factory()->carreta()->create();

    contar($carreta, ['Faja de 4" con chicharra' => [8, 0], 'Faja de 2" con chicharra' => [4, 0]], '2026-10-01');
    // El primer conteo es su saldo inicial (8 y 4). El segundo comprueba:
    // encuentra dañadas, que no cuentan para el mínimo.
    contar($carreta, ['Faja de 4" con chicharra' => [6, 2], 'Faja de 2" con chicharra' => [3, 1]], '2026-10-15');

    $estados = app(EquipamientoDeUnidades::class)->paraFlota(collect([$carreta, $nunca]));
    $items = collect($estados[$carreta->id]['items'])->keyBy('nombre');

    expect($estados[$carreta->id]['estado'])->toBe(EstadoEquipamiento::Falta)
        ->and($estados[$carreta->id]['faltan'])->toBe(3)
        ->and($items['Faja de 4" con chicharra'])->toMatchArray(['buenas' => 6, 'danadas' => 2, 'faltan' => 2])
        ->and($items['Faja de 4" sin chicharra'])->toMatchArray(['buenas' => 0, 'faltan' => 0])
        ->and($estados[$nunca->id]['estado'])->toBe(EstadoEquipamiento::SinContar);

    // Borrar el último conteo la deja con el anterior, que estaba completo.
    actingAs(contador())
        ->delete(route('mantenimiento.equipamiento.destroy', ConteoEquipamiento::query()->latest('fecha')->first()))
        ->assertSessionHasNoErrors();

    expect(app(EquipamientoDeUnidades::class)->paraVehiculo($carreta)['estado'])->toBe(EstadoEquipamiento::Completo);
});

it('says counted, not complete, while no item has a minimum', function (): void {
    $carreta = Vehiculo::factory()->carreta()->create();
    contar($carreta, ['Faja de 4" con chicharra' => [3, 0]]);

    expect(app(EquipamientoDeUnidades::class)->paraVehiculo($carreta)['estado'])->toBe(EstadoEquipamiento::Contado);
});

it('counts on each unit only the equipment that applies to it', function (): void {
    $tracto = Vehiculo::factory()->create();

    // Las fajas son de la carreta: en un tracto no se cuentan.
    actingAs(contador())
        ->post(route('mantenimiento.equipamiento.store', $tracto), [
            'fecha' => '2026-10-15',
            'items' => [['id' => faja('Faja de 4" con chicharra')->id, 'buenas' => 1, 'danadas' => 0]],
        ])
        ->assertSessionHasErrors('items.0.id');

    // El tracto tiene lo suyo: extintor, botiquín, conos y herramientas.
    actingAs(contador())
        ->get(route('mantenimiento.equipamiento.show', $tracto))
        ->assertInertia(fn ($page) => $page->has('carreta.items', 11)->where('carreta.items.0.nombre', 'Extintor'));
});

it('lists the carretas missing something first, and shows each one with its counts', function (): void {
    faja('Faja de 4" con chicharra')->update(['minimo' => 8]);

    $completa = Vehiculo::factory()->carreta()->create(['placa' => 'AAA-111']);
    contar($completa, ['Faja de 4" con chicharra' => [8, 0]]);
    $incompleta = Vehiculo::factory()->carreta()->create(['placa' => 'ZZZ-999']);
    contar($incompleta, ['Faja de 4" con chicharra' => [5, 0]]);
    $tracto = Vehiculo::factory()->create();

    actingAs(contador())
        ->get(route('mantenimiento.equipamiento.index'))
        ->assertInertia(fn ($page) => $page
            ->component('mantenimiento/equipamiento/index')
            // Las dos carretas y el tracto, que tiene su propio equipamiento.
            ->has('carretas', 3)
            ->where('carretas.0.id', $incompleta->id)
            ->where('carretas.0.faltan', 3)
            ->where('carretas.1.id', $tracto->id)
            ->where('carretas.1.estado', 'sin_contar')
            ->where('carretas.2.estado', 'completo')
            ->has('catalogo', 15)
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
    ])->and(ItemEquipamiento::query()->count())->toBe(16);
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

it('shows the survey screen with the kit of the unit to whoever can count', function (): void {
    $tracto = Vehiculo::factory()->create();

    actingAs(contador())
        ->get(route('mantenimiento.equipamiento.levantamiento', $tracto))
        ->assertInertia(fn ($page) => $page
            ->component('mantenimiento/equipamiento/levantamiento')
            ->has('carreta.items', 11)
            ->where('carreta.items.0.minimo', 1)
            ->where('maximoFotos', 3)
        );

    $rol = Role::findOrCreate('mirador', 'web');
    $rol->syncPermissions([Permiso::MantenimientoVer->value]);

    actingAs(User::factory()->create()->assignRole($rol))
        ->get(route('mantenimiento.equipamiento.levantamiento', $tracto))
        ->assertForbidden();
});

it('saves the survey with its photos, notes and expiry, without touching the warehouse', function (): void {
    Storage::fake('public');

    $tracto = Vehiculo::factory()->create();
    $extintor = faja('Extintor');
    $pala = faja('Pala');
    $botiquin = faja('Botiquín');

    actingAs(contador())
        ->post(route('mantenimiento.equipamiento.store', $tracto), [
            'fecha' => '2026-11-20',
            'items' => [
                ['id' => $extintor->id, 'buenas' => 1, 'danadas' => 0, 'vence_el' => '2027-03-14', 'fotos' => [UploadedFile::fake()->image('extintor.jpg', 800, 600)]],
                ['id' => $pala->id, 'buenas' => 0, 'danadas' => 0, 'nota' => 'No está en la cabina'],
                ['id' => $botiquin->id, 'buenas' => 0, 'danadas' => 1, 'nota' => 'Incompleto'],
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('mantenimiento.equipamiento.show', $tracto));

    $conteo = ConteoEquipamiento::query()->where('vehiculo_id', $tracto->id)->with('detalles')->sole();
    $detalles = $conteo->detalles->keyBy('item_equipamiento_id');

    expect($detalles[$extintor->id]->getMedia('fotos'))->toHaveCount(1)
        ->and($detalles[$pala->id]->nota)->toBe('No está en la cabina')
        ->and($detalles[$pala->id]->getMedia('fotos'))->toHaveCount(0)
        // El extintor va pieza por pieza: su vencimiento queda en la pieza.
        ->and(VencimientoEquipamiento::query()->count())->toBe(0)
        ->and(PiezaEquipamiento::query()->where('vehiculo_id', $tracto->id)->orderBy('codigo')->get()->mapWithKeys(fn (PiezaEquipamiento $pieza): array => [$pieza->codigo => $pieza->vence_el?->toDateString()])->all())
        ->toBe(['BO-001' => null, 'EX-001' => '2027-03-14'])
        // Lo que ya tenía queda como saldo inicial; lo que falta, no.
        ->and(MovimientoEquipamiento::query()->where('vehiculo_id', $tracto->id)->pluck('cantidad', 'item_equipamiento_id')->all())
        ->toBe([$extintor->id => 1, $botiquin->id => 1]);

    $items = collect(app(EquipamientoDeUnidades::class)->paraVehiculo($tracto)['items'])->keyBy('nombre');

    expect($items['Extintor'])->toMatchArray(['asignado' => 1, 'faltan' => 0, 'vence_el' => '2027-03-14'])
        ->and($items['Pala'])->toMatchArray(['asignado' => 0, 'faltan' => 1])
        // El dañado está en la unidad, pero no cuenta para lo que debe llevar.
        ->and($items['Botiquín'])->toMatchArray(['asignado' => 1, 'danadas' => 1, 'faltan' => 1]);

    actingAs(contador())
        ->get(route('mantenimiento.equipamiento.show', $tracto))
        ->assertInertia(fn ($page) => $page
            ->has("fotos.{$extintor->id}", 1)
            ->missing("fotos.{$pala->id}")
            ->where('conteos.0.detalles', fn ($detalles): bool => collect($detalles)->pluck('nota', 'item')->all() === ['Extintor' => null, 'Pala' => 'No está en la cabina', 'Botiquín' => 'Incompleto']
                && collect($detalles)->mapWithKeys(fn (array $detalle): array => [$detalle['item'] => count($detalle['fotos'])])->all() === ['Extintor' => 1, 'Pala' => 0, 'Botiquín' => 0])
        );
});

it('rejects more photos than an item admits and files that are not photos', function (): void {
    Storage::fake('public');

    $tracto = Vehiculo::factory()->create();
    $extintor = faja('Extintor');
    $foto = fn (): UploadedFile => UploadedFile::fake()->image('foto.jpg', 400, 300);

    actingAs(contador())
        ->post(route('mantenimiento.equipamiento.store', $tracto), [
            'fecha' => '2026-11-20',
            'items' => [['id' => $extintor->id, 'buenas' => 1, 'danadas' => 0, 'fotos' => [$foto(), $foto(), $foto(), $foto()]]],
        ])
        ->assertSessionHasErrors('items.0.fotos');

    actingAs(contador())
        ->post(route('mantenimiento.equipamiento.store', $tracto), [
            'fecha' => '2026-11-20',
            'items' => [['id' => $extintor->id, 'buenas' => 1, 'danadas' => 0, 'fotos' => [UploadedFile::fake()->createWithContent('nota.txt', 'no es una foto')]]],
        ])
        ->assertSessionHasErrors('items.0.fotos.0');

    expect(ConteoEquipamiento::query()->count())->toBe(0);
});

/** Un tracto con su levantamiento hecho: extintor, botiquín y pala. */
function tractoLevantado(): Vehiculo
{
    $tracto = Vehiculo::factory()->create();
    contar($tracto, ['Extintor' => [1, 0], 'Botiquín' => [1, 0], 'Pala' => [1, 0]]);

    return $tracto;
}

it('gives its own code to each piece found in the survey, and only once', function (): void {
    $uno = tractoLevantado();
    $otro = tractoLevantado();

    expect(PiezaEquipamiento::query()->where('vehiculo_id', $uno->id)->orderBy('codigo')->pluck('codigo')->all())->toBe(['BO-001', 'EX-001'])
        ->and(PiezaEquipamiento::query()->where('vehiculo_id', $otro->id)->orderBy('codigo')->pluck('codigo')->all())->toBe(['BO-002', 'EX-002']);

    // Revisarlo de nuevo no le cambia el código ni le da otro.
    contar($uno, ['Extintor' => [1, 0], 'Botiquín' => [0, 1], 'Pala' => [1, 0]], '2026-10-20');

    $filas = collect(app(EquipamientoDeUnidades::class)->paraVehiculo($uno)['items'])->keyBy('nombre');

    expect(PiezaEquipamiento::query()->count())->toBe(4)
        ->and($filas['Extintor'])->toMatchArray(['por_pieza' => true, 'sin_codigo' => 0])
        ->and($filas['Extintor']['piezas'][0]['codigo'])->toBe('EX-001')
        // La pala va por cantidad: no lleva código.
        ->and($filas['Pala'])->toMatchArray(['por_pieza' => false, 'piezas' => [], 'sin_codigo' => 0]);
});

it('keeps the data and the expiry of each piece, and warns with the one that expires first', function (): void {
    $tracto = tractoLevantado();
    $extintor = PiezaEquipamiento::query()->where('codigo', 'EX-001')->sole();

    actingAs(contador())
        ->put(route('mantenimiento.equipamiento.piezas.update', $extintor), ['descripcion' => 'PQS de 6 kg', 'serie' => 'A-4471', 'vence_el' => '2020-01-31'])
        ->assertSessionHasNoErrors();

    $estado = app(EquipamientoDeUnidades::class)->paraVehiculo($tracto);
    $fila = collect($estado['items'])->firstWhere('nombre', 'Extintor');

    expect($fila)->toMatchArray(['vence_el' => '2020-01-31', 'vencido' => true])
        ->and($fila['piezas'][0])->toMatchArray(['descripcion' => 'PQS de 6 kg', 'serie' => 'A-4471', 'vencido' => true])
        ->and($estado['estado'])->toBe(EstadoEquipamiento::Falta);

    $rol = Role::findOrCreate('mirador', 'web');
    $rol->syncPermissions([Permiso::MantenimientoVer->value]);

    actingAs(User::factory()->create()->assignRole($rol))
        ->put(route('mantenimiento.equipamiento.piezas.update', $extintor), ['descripcion' => 'Otro'])
        ->assertForbidden();
});

it('moves or writes off a piece by its code, and brings it back when the movement is cancelled', function (): void {
    $origen = tractoLevantado();
    $destino = Vehiculo::factory()->create();
    $extintor = PiezaEquipamiento::query()->where('codigo', 'EX-001')->sole();
    $item = faja('Extintor');

    $traslado = fn (array $datos) => actingAs(contador())->post(route('mantenimiento.equipamiento.traslados.store', $origen), [
        'item_equipamiento_id' => $item->id, 'fecha' => '2026-10-20', 'cantidad' => 1, 'vehiculo_destino_id' => $destino->id, ...$datos,
    ]);

    // Sin decir cuál, no se mueve.
    $traslado([])->assertSessionHasErrors('pieza_equipamiento_id');
    $traslado(['pieza_equipamiento_id' => $extintor->id])->assertSessionHasNoErrors();

    expect($extintor->refresh()->vehiculo_id)->toBe($destino->id)
        ->and(app(EquipamientoDeUnidades::class)->asignado($origen, $item))->toBe(0)
        ->and(app(EquipamientoDeUnidades::class)->asignado($destino, $item))->toBe(1);

    actingAs(contador())->post(route('mantenimiento.equipamiento.bajas.store', $destino), [
        'item_equipamiento_id' => $item->id, 'fecha' => '2026-10-21', 'cantidad' => 1, 'pieza_equipamiento_id' => $extintor->id, 'motivo' => 'Descargado y sin recarga posible',
    ])->assertSessionHasNoErrors();

    expect($extintor->refresh()->baja_at)->not->toBeNull()
        ->and(collect(app(EquipamientoDeUnidades::class)->paraVehiculo($destino)['items'])->firstWhere('id', $item->id))->toMatchArray(['asignado' => 0, 'piezas' => []]);

    $admin = User::factory()->create()->assignRole(tap(Role::findOrCreate('anulador', 'web'))->syncPermissions([Permiso::MantenimientoVer->value, Permiso::MantenimientoAnular->value]));
    $movimientos = MovimientoEquipamiento::query()->where('pieza_equipamiento_id', $extintor->id)->orderBy('id')->get();

    // Primero se deshace lo último: la baja; recién después, el traslado.
    actingAs($admin)->post(route('mantenimiento.equipamiento.movimientos.anular', $movimientos[0]), ['motivo' => 'Se registró por error'])->assertSessionHasErrors('motivo');
    actingAs($admin)->post(route('mantenimiento.equipamiento.movimientos.anular', $movimientos[1]), ['motivo' => 'Se registró por error'])->assertSessionHasNoErrors();
    actingAs($admin)->post(route('mantenimiento.equipamiento.movimientos.anular', $movimientos[0]), ['motivo' => 'Se registró por error'])->assertSessionHasNoErrors();

    expect($extintor->refresh())->vehiculo_id->toBe($origen->id)->baja_at->toBeNull();
});

it('puts a code on a piece that arrived without one, and on new items marked piece by piece', function (): void {
    $tracto = Vehiculo::factory()->create();
    $gata = faja('Gata');
    $gata->update(['por_pieza' => true]);

    // Llegó sin pasar por un levantamiento: la unidad la tiene, sin código.
    MovimientoEquipamiento::create(['vehiculo_id' => $tracto->id, 'item_equipamiento_id' => $gata->id, 'tipo' => 'saldo_inicial', 'cantidad' => 1, 'fecha' => '2026-10-15']);

    expect(collect(app(EquipamientoDeUnidades::class)->paraVehiculo($tracto)['items'])->firstWhere('id', $gata->id)['sin_codigo'])->toBe(1);

    $poner = fn (ItemEquipamiento $item) => actingAs(contador())->post(route('mantenimiento.equipamiento.piezas.store', $tracto), ['item_equipamiento_id' => $item->id]);

    $poner($gata)->assertSessionHasNoErrors();
    $poner($gata)->assertSessionHasNoErrors();
    // La pala va por cantidad.
    $poner(faja('Pala'))->assertSessionHasErrors('item_equipamiento_id');

    // Sus letras salen del nombre; una sola pieza aunque se pida dos veces.
    expect(PiezaEquipamiento::query()->pluck('codigo')->all())->toBe(['GA-001'])
        ->and($gata->refresh()->prefijo)->toBe('GA');
});
