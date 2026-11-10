<?php

use App\Enums\EstadoEquipamiento;
use App\Enums\OrigenLlanta;
use App\Models\Articulo;
use App\Models\CategoriaArticulo;
use App\Models\ConteoEquipamiento;
use App\Models\IngresoAlmacen;
use App\Models\ItemEquipamiento;
use App\Models\Llanta;
use App\Models\MovimientoEquipamiento;
use App\Models\Proveedor;
use App\Models\SalidaAlmacen;
use App\Models\Vehiculo;
use App\Services\ArticulosDeEquipamiento;
use App\Services\EquipamientoDeUnidades;
use App\Services\Kardex;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

/*
 * Las llantas y el equipamiento de las unidades salen del almacén: una llanta
 * se monta contra su vale, y lo que sale con vale a nombre de una unidad queda
 * asignado a ella. Entre unidades se mueven sin volver al almacén, con
 * historial. El reloj queda en noviembre de 2026, el mes abierto del almacén.
 */
beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-11-09 17:00:00'));
    Storage::fake('public');
    Storage::fake('local');
});

/** Entra stock del artículo, por una compra. */
function stockDe(Articulo $articulo, float $cantidad, float $costo = 100): void
{
    $ingreso = IngresoAlmacen::factory()->create(['fecha' => '2026-11-02']);
    $ingreso->detalles()->create([
        'articulo_id' => $articulo->id,
        'cantidad' => $cantidad,
        'precio_unitario' => $costo,
        'factor' => 1,
        'cantidad_base' => $cantidad,
        'costo_unitario' => $costo,
        'costo_total' => round($cantidad * $costo, 2),
    ]);

    app(Kardex::class)->registrarIngreso($ingreso);
}

/** Una llanta del catálogo del almacén, con stock. */
function llantaDeAlmacen(float $stock = 4): Articulo
{
    $articulo = Articulo::factory()->create([
        'nombre' => 'LLANTA 295/80R22.5',
        'marca' => 'Michelin',
        'categoria_articulo_id' => CategoriaArticulo::query()->where('clave', 'llantas')->value('id'),
    ]);

    stockDe($articulo, $stock, 1450);

    return $articulo;
}

/** Un vale de salida a nombre de la unidad, como lo emite el almacén. */
function valeALaUnidad(Vehiculo $unidad, Articulo $articulo, float $cantidad): SalidaAlmacen
{
    actingAs(actorConRol('admin'))
        ->post(route('almacen.salidas.store'), [
            'fecha' => '2026-11-05',
            'entregado_a' => 'Juan Quispe',
            'destino' => 'unidad',
            'vehiculo_id' => $unidad->id,
            'detalles' => [['articulo_id' => $articulo->id, 'cantidad' => $cantidad]],
        ])
        ->assertSessionHasNoErrors();

    return SalidaAlmacen::query()->latest('id')->firstOrFail();
}

/**
 * @return array<string, mixed>
 */
function montaje(string $posicion, array $extra): array
{
    return ['tipo' => 'instalacion', 'posicion' => $posicion, 'fecha' => '2026-11-06', 'condicion' => 'nueva', ...$extra];
}

function itemDeEquipo(string $nombre): ItemEquipamiento
{
    return ItemEquipamiento::query()->where('nombre', $nombre)->sole();
}

/**
 * Cuenta el equipamiento de la unidad, como taller.
 *
 * @param  array<string, array{0: int, 1: int}>  $cantidades  buenas y dañadas por ítem
 */
function conteoDe(Vehiculo $unidad, array $cantidades)
{
    return actingAs(actorConRol('taller'))
        ->post(route('mantenimiento.equipamiento.store', $unidad), [
            'fecha' => '2026-11-06',
            'items' => collect($cantidades)
                ->map(fn (array $cantidad, string $nombre): array => ['id' => itemDeEquipo($nombre)->id, 'buenas' => $cantidad[0], 'danadas' => $cantidad[1]])
                ->values()
                ->all(),
        ]);
}

function asignadoDe(Vehiculo $unidad, string $nombre): int
{
    return app(EquipamientoDeUnidades::class)->asignado($unidad, itemDeEquipo($nombre));
}

// ─── Llantas ────────────────────────────────────────────────────────────────

it('does not mount a tire that did not leave the warehouse with a vale for that unit', function (): void {
    $tracto = Vehiculo::factory()->create();
    $otro = Vehiculo::factory()->create();
    $llanta = llantaDeAlmacen();
    $filtro = Articulo::factory()->create();
    stockDe($filtro, 5);

    // Sin vale, taller no puede: cargar una que «ya estaba» es del administrador.
    actingAs(actorConRol('taller'))
        ->post(route('mantenimiento.llantas.store', $tracto), montaje('1', ['origen' => 'previa', 'marca' => 'Michelin']))
        ->assertSessionHasErrors('origen');

    actingAs(actorConRol('taller'))
        ->post(route('mantenimiento.llantas.store', $tracto), montaje('1', ['origen' => 'almacen']))
        ->assertSessionHasErrors('detalle_salida_id');

    // Con el vale de otra unidad, tampoco; ni con un artículo que no es llanta.
    $ajeno = valeALaUnidad($otro, $llanta, 1)->detalles()->sole();
    $noEsLlanta = valeALaUnidad($tracto, $filtro, 1)->detalles()->sole();

    foreach ([$ajeno, $noEsLlanta] as $linea) {
        actingAs(actorConRol('taller'))
            ->post(route('mantenimiento.llantas.store', $tracto), montaje('1', ['origen' => 'almacen', 'detalle_salida_id' => $linea->id]))
            ->assertSessionHasErrors('detalle_salida_id');
    }

    expect(Llanta::query()->count())->toBe(0);
});

it('mounts each tire of a vale once, with the data of its article', function (): void {
    $tracto = Vehiculo::factory()->create();
    $articulo = llantaDeAlmacen();
    $linea = valeALaUnidad($tracto, $articulo, 2)->detalles()->sole();

    actingAs(actorConRol('taller'))
        ->get(route('mantenimiento.llantas.show', $tracto))
        ->assertInertia(fn (Assert $page) => $page
            ->where('valesDeAlmacen.0.detalle_salida_id', $linea->id)
            ->where('valesDeAlmacen.0.disponibles', 2)
            ->where('puedeCargaInicial', false));

    foreach (['1', '2'] as $posicion) {
        actingAs(actorConRol('taller'))
            ->post(route('mantenimiento.llantas.store', $tracto), montaje($posicion, ['origen' => 'almacen', 'detalle_salida_id' => $linea->id, 'codigo' => "DOT-{$posicion}"]))
            ->assertSessionHasNoErrors();
    }

    $montada = Llanta::query()->where('posicion', '1')->sole();

    expect($montada->origen)->toBe(OrigenLlanta::Almacen)
        ->and($montada->articulo_id)->toBe($articulo->id)
        ->and($montada->detalle_salida_id)->toBe($linea->id)
        ->and($montada->marca)->toBe('Michelin')
        ->and($montada->codigo)->toBe('DOT-1');

    // Salieron dos: la tercera necesita otro vale.
    actingAs(actorConRol('taller'))
        ->post(route('mantenimiento.llantas.store', $tracto), montaje('3', ['origen' => 'almacen', 'detalle_salida_id' => $linea->id]))
        ->assertSessionHasErrors('detalle_salida_id');

    expect(Llanta::query()->count())->toBe(2);
});

it('does not return or void in the warehouse a tire that is already mounted', function (): void {
    $tracto = Vehiculo::factory()->create();
    $vale = valeALaUnidad($tracto, llantaDeAlmacen(), 2);
    $linea = $vale->detalles()->sole();

    actingAs(actorConRol('taller'))
        ->post(route('mantenimiento.llantas.store', $tracto), montaje('1', ['origen' => 'almacen', 'detalle_salida_id' => $linea->id]))
        ->assertSessionHasNoErrors();

    // Salieron 2 y hay 1 montada: solo puede volver 1.
    actingAs(actorConRol('admin'))
        ->post(route('almacen.salidas.devolver', $vale), ['fecha' => '2026-11-07', 'detalles' => [['detalle_salida_id' => $linea->id, 'cantidad' => 2]]])
        ->assertSessionHasErrors('detalles.0.cantidad');

    actingAs(actorConRol('admin'))
        ->post(route('almacen.salidas.anular', $vale), ['motivo' => 'Se emitió por error'])
        ->assertSessionHasErrors('motivo');

    actingAs(actorConRol('admin'))
        ->post(route('almacen.salidas.devolver', $vale), ['fecha' => '2026-11-07', 'detalles' => [['detalle_salida_id' => $linea->id, 'cantidad' => 1]]])
        ->assertSessionHasNoErrors();

    // Devuelta la otra, a esa línea ya no le queda ninguna por montar.
    actingAs(actorConRol('taller'))
        ->post(route('mantenimiento.llantas.store', $tracto), montaje('2', ['origen' => 'almacen', 'detalle_salida_id' => $linea->id]))
        ->assertSessionHasErrors('detalle_salida_id');
});

it('moves a tire from one unit to another without the warehouse, keeping its history', function (): void {
    $tracto = Vehiculo::factory()->create(['placa' => 'AAA-111']);
    $carreta = Vehiculo::factory()->carreta()->create(['placa' => 'BBB-222']);
    $linea = valeALaUnidad($tracto, llantaDeAlmacen(), 1)->detalles()->sole();

    actingAs(actorConRol('taller'))->post(route('mantenimiento.llantas.store', $tracto), montaje('1', ['origen' => 'almacen', 'detalle_salida_id' => $linea->id]));
    $llanta = Llanta::query()->sole();

    // Montada no se puede llevar a otra unidad: primero se retira.
    actingAs(actorConRol('taller'))
        ->post(route('mantenimiento.llantas.store', $carreta), montaje('R1', ['origen' => 'retirada', 'llanta_id' => $llanta->id, 'condicion' => 'usada']))
        ->assertSessionHasErrors('llanta_id');

    actingAs(actorConRol('taller'))
        ->post(route('mantenimiento.llantas.store', $tracto), ['tipo' => 'retiro', 'posicion' => '1', 'fecha' => '2026-11-07', 'motivo_retiro' => 'otro'])
        ->assertSessionHasNoErrors();

    actingAs(actorConRol('taller'))
        ->get(route('mantenimiento.llantas.show', $carreta))
        ->assertInertia(fn (Assert $page) => $page->where('retiradas.0.id', $llanta->id)->where('retiradas.0.placa', 'AAA-111'));

    actingAs(actorConRol('taller'))
        ->post(route('mantenimiento.llantas.store', $carreta), montaje('R1', ['origen' => 'retirada', 'llanta_id' => $llanta->id, 'condicion' => 'usada', 'fecha' => '2026-11-08']))
        ->assertSessionHasNoErrors();

    $llanta->refresh();
    $movimientos = $llanta->movimientos()->orderBy('id')->get();

    // Es la misma llanta, ahora en la carreta, con sus tres movimientos.
    expect(Llanta::query()->count())->toBe(1)
        ->and($llanta->vehiculo_id)->toBe($carreta->id)
        ->and($llanta->posicion)->toBe('R1')
        ->and($llanta->retirada_el)->toBeNull()
        ->and($llanta->detalle_salida_id)->toBe($linea->id)
        ->and($movimientos)->toHaveCount(3)
        ->and($movimientos[2]->vehiculo_id)->toBe($carreta->id)
        ->and($movimientos[2]->vehiculo_origen_id)->toBe($tracto->id);

    actingAs(actorConRol('taller'))
        ->get(route('mantenimiento.llantas.show', $carreta))
        ->assertInertia(fn (Assert $page) => $page->where('movimientos.0.viene_de', 'AAA-111'));
});

it('does not remount a tire that was retired because of damage', function (): void {
    $tracto = Vehiculo::factory()->create();
    $danada = Llanta::factory()->create(['retirada_el' => '2026-11-01', 'motivo_retiro' => 'dano']);

    actingAs(actorConRol('taller'))
        ->post(route('mantenimiento.llantas.store', $tracto), montaje('1', ['origen' => 'retirada', 'llanta_id' => $danada->id, 'condicion' => 'usada']))
        ->assertSessionHasErrors('llanta_id');
});

it('lets only the admin load the tires that were already mounted before the warehouse', function (): void {
    $tracto = Vehiculo::factory()->create();

    actingAs(actorConRol('admin'))
        ->post(route('mantenimiento.llantas.store', $tracto), montaje('1', ['origen' => 'previa', 'marca' => 'Goodyear', 'medida' => '295/80R22.5', 'condicion' => 'usada', 'fecha' => '2026-11-01']))
        ->assertSessionHasNoErrors();

    $llanta = Llanta::query()->sole();

    expect($llanta->origen)->toBe(OrigenLlanta::Previa)
        ->and($llanta->detalle_salida_id)->toBeNull()
        ->and($llanta->marca)->toBe('Goodyear');
});

// ─── Equipamiento ───────────────────────────────────────────────────────────

it('takes the first count of a unit as what it already had, and from then on only checks', function (): void {
    $carreta = Vehiculo::factory()->carreta()->create();

    conteoDe($carreta, ['Faja de 4" con chicharra' => [8, 0]])->assertSessionHasNoErrors();

    expect(asignadoDe($carreta, 'Faja de 4" con chicharra'))->toBe(8)
        ->and(MovimientoEquipamiento::query()->sole()->tipo->value)->toBe('saldo_inicial');

    // Aparecen 10 donde había 8: las dos de más no salieron de ningún lado.
    conteoDe($carreta, ['Faja de 4" con chicharra' => [10, 0]])->assertSessionHasErrors('items.0.buenas');

    // Se encuentran 6: las 2 que faltan quedan a la vista, sin explicar.
    conteoDe($carreta, ['Faja de 4" con chicharra' => [6, 0]])->assertSessionHasNoErrors();

    $fila = collect(app(EquipamientoDeUnidades::class)->paraVehiculo($carreta)['items'])->firstWhere('nombre', 'Faja de 4" con chicharra');

    expect($fila)->toMatchArray(['asignado' => 8, 'sin_explicar' => 2]);
});

it('gives each equipment item its article in the warehouse, once', function (): void {
    expect(app(ArticulosDeEquipamiento::class)->asegurar())->toBe(11)
        ->and(app(ArticulosDeEquipamiento::class)->asegurar())->toBe(0);

    $extintor = itemDeEquipo('Extintor')->articulo;

    expect($extintor->nombre)->toBe('EXTINTOR')
        ->and($extintor->categoria->clave)->toBe('equipamiento')
        ->and(Articulo::query()->where('categoria_articulo_id', $extintor->categoria_articulo_id)->count())->toBe(11);

    // La categoría se puede renombrar, no quitar: Mantenimiento la necesita.
    actingAs(actorConRol('admin'))
        ->delete(route('almacen.categorias.destroy', $extintor->categoria))
        ->assertSessionHasErrors('mover_a');
});

it('assigns to the unit what leaves the warehouse with a vale in its name', function (): void {
    app(ArticulosDeEquipamiento::class)->asegurar();
    $tracto = Vehiculo::factory()->create();
    $conos = itemDeEquipo('Conos de seguridad')->articulo;
    stockDe($conos, 10, 25);

    $vale = valeALaUnidad($tracto, $conos, 4);

    expect(asignadoDe($tracto, 'Conos de seguridad'))->toBe(4);

    // Lo que vuelve al almacén deja de estar en la unidad.
    actingAs(actorConRol('admin'))
        ->post(route('almacen.salidas.devolver', $vale), ['fecha' => '2026-11-07', 'detalles' => [['detalle_salida_id' => $vale->detalles()->value('id'), 'cantidad' => 1]]])
        ->assertSessionHasNoErrors();

    expect(asignadoDe($tracto, 'Conos de seguridad'))->toBe(3);

    // Ya tiene historial: el conteo no puede encontrar más de 3.
    conteoDe($tracto, ['Conos de seguridad' => [4, 0]])->assertSessionHasErrors('items.0.buenas');
    conteoDe($tracto, ['Conos de seguridad' => [3, 0]])->assertSessionHasNoErrors();

    expect(MovimientoEquipamiento::query()->count())->toBe(0);
});

it('moves equipment between units and keeps it in the history of both', function (): void {
    $origen = Vehiculo::factory()->carreta()->create(['placa' => 'AAA-111']);
    $destino = Vehiculo::factory()->carreta()->create(['placa' => 'BBB-222']);
    $tracto = Vehiculo::factory()->create();
    conteoDe($origen, ['Faja de 4" con chicharra' => [8, 0]]);
    $faja = itemDeEquipo('Faja de 4" con chicharra');

    $traslado = fn (array $datos) => actingAs(actorConRol('taller'))
        ->post(route('mantenimiento.equipamiento.traslados.store', $origen), ['item_equipamiento_id' => $faja->id, 'fecha' => '2026-11-07', ...$datos]);

    // No más de lo que tiene, ni a una unidad a la que no le toca.
    $traslado(['cantidad' => 9, 'vehiculo_destino_id' => $destino->id])->assertSessionHasErrors('cantidad');
    $traslado(['cantidad' => 2, 'vehiculo_destino_id' => $tracto->id])->assertSessionHasErrors('vehiculo_destino_id');
    $traslado(['cantidad' => 3, 'vehiculo_destino_id' => $destino->id])->assertSessionHasNoErrors();

    expect(asignadoDe($origen, 'Faja de 4" con chicharra'))->toBe(5)
        ->and(asignadoDe($destino, 'Faja de 4" con chicharra'))->toBe(3);

    actingAs(actorConRol('taller'))
        ->get(route('mantenimiento.equipamiento.show', $destino))
        ->assertInertia(fn (Assert $page) => $page
            ->where('movimientos.0.tipo', 'traslado')
            ->where('movimientos.0.entra', true)
            ->where('movimientos.0.otra_placa', 'AAA-111')
            ->where('puedeAnular', false));

    // Anular es del administrador, y deja todo como estaba.
    $movimiento = MovimientoEquipamiento::query()->where('tipo', 'traslado')->sole();

    actingAs(actorConRol('taller'))
        ->post(route('mantenimiento.equipamiento.movimientos.anular', $movimiento), ['motivo' => 'Se registró mal'])
        ->assertForbidden();

    actingAs(actorConRol('admin'))
        ->post(route('mantenimiento.equipamiento.movimientos.anular', $movimiento), ['motivo' => 'Se registró en la carreta equivocada'])
        ->assertSessionHasNoErrors();

    expect(asignadoDe($origen, 'Faja de 4" con chicharra'))->toBe(8)
        ->and(asignadoDe($destino, 'Faja de 4" con chicharra'))->toBe(0)
        ->and($movimiento->refresh()->estaAnulado())->toBeTrue();
});

it('writes off what is no longer usable, saying why', function (): void {
    $carreta = Vehiculo::factory()->carreta()->create();
    itemDeEquipo('Faja de 4" con chicharra')->update(['minimo' => 8]);
    conteoDe($carreta, ['Faja de 4" con chicharra' => [6, 2]]);
    $faja = itemDeEquipo('Faja de 4" con chicharra');

    // Con 2 dañadas le faltan 2 buenas para el mínimo.
    expect(app(EquipamientoDeUnidades::class)->paraVehiculo($carreta)['faltan'])->toBe(2);

    $baja = fn (array $datos) => actingAs(actorConRol('taller'))
        ->post(route('mantenimiento.equipamiento.bajas.store', $carreta), ['item_equipamiento_id' => $faja->id, 'fecha' => '2026-11-07', ...$datos]);

    $baja(['cantidad' => 2])->assertSessionHasErrors('motivo');
    $baja(['cantidad' => 9, 'motivo' => 'Fajas rotas en la descarga'])->assertSessionHasErrors('cantidad');
    $baja(['cantidad' => 2, 'motivo' => 'Fajas rotas en la descarga'])->assertSessionHasNoErrors();

    $estado = app(EquipamientoDeUnidades::class)->paraVehiculo($carreta);
    $fila = collect($estado['items'])->firstWhere('id', $faja->id);

    // Quedan 6, todas buenas: siguen faltando 2, que ahora salen del almacén.
    expect($fila)->toMatchArray(['asignado' => 6, 'buenas' => 6, 'danadas' => 0, 'faltan' => 2])
        ->and($estado['estado'])->toBe(EstadoEquipamiento::Falta);
});

it('warns when the extinguisher of a unit has expired', function (): void {
    $tracto = Vehiculo::factory()->create();
    conteoDe($tracto, ['Extintor' => [1, 0], 'Pala' => [1, 0]]);

    $vence = fn (string $item, string $fecha) => actingAs(actorConRol('taller'))
        ->put(route('mantenimiento.equipamiento.vencimientos.update', $tracto), ['item_equipamiento_id' => itemDeEquipo($item)->id, 'vence_el' => $fecha]);

    // Una pala no vence.
    $vence('Pala', '2027-01-01')->assertSessionHasErrors('item_equipamiento_id');

    $vence('Extintor', '2026-11-20')->assertSessionHasNoErrors();
    $fila = fn (): array => collect(app(EquipamientoDeUnidades::class)->paraVehiculo($tracto)['items'])->firstWhere('nombre', 'Extintor');

    expect($fila())->toMatchArray(['vence_el' => '2026-11-20', 'por_vencer' => true, 'vencido' => false]);

    $vence('Extintor', '2026-10-31')->assertSessionHasNoErrors();

    expect($fila())->toMatchArray(['vencido' => true, 'por_vencer' => false])
        ->and(app(EquipamientoDeUnidades::class)->paraVehiculo($tracto))->toMatchArray(['vencidos' => 1, 'estado' => EstadoEquipamiento::Falta]);
});

it('does not delete the count that set the opening balance once things moved from it', function (): void {
    $origen = Vehiculo::factory()->carreta()->create();
    $destino = Vehiculo::factory()->carreta()->create();
    conteoDe($origen, ['Faja de 4" con chicharra' => [8, 0]]);

    actingAs(actorConRol('taller'))->post(route('mantenimiento.equipamiento.traslados.store', $origen), [
        'item_equipamiento_id' => itemDeEquipo('Faja de 4" con chicharra')->id, 'fecha' => '2026-11-07', 'cantidad' => 3, 'vehiculo_destino_id' => $destino->id,
    ])->assertSessionHasNoErrors();

    actingAs(actorConRol('taller'))
        ->delete(route('mantenimiento.equipamiento.destroy', ConteoEquipamiento::query()->where('vehiculo_id', $origen->id)->sole()))
        ->assertSessionHasErrors('conteo');

    expect(asignadoDe($origen, 'Faja de 4" con chicharra'))->toBe(5);
});

// ─── Vencimientos en el almacén ─────────────────────────────────────────────

it('keeps the expiry date of what enters the warehouse and warns when it is close', function (): void {
    config(['transpaty.compras.vale_obligatorio_desde' => null]);
    $extintor = Articulo::factory()->create(['nombre' => 'EXTINTOR PQS 6 KG']);
    $filtro = Articulo::factory()->create();

    actingAs(actorConRol('almacen'))
        ->post(route('almacen.ingresos.store'), [
            'fecha' => '2026-11-09',
            'fecha_emision' => '2026-11-09',
            'proveedor_id' => Proveedor::factory()->create()->id,
            'tipo_comprobante' => 'factura',
            'serie_numero' => 'F001-00123',
            'moneda' => 'PEN',
            'precios_con_igv' => true,
            'total_comprobante' => 400,
            'comprobante' => UploadedFile::fake()->createWithContent('factura.pdf', "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n"),
            'detalles' => [
                ['articulo_id' => $extintor->id, 'cantidad' => 2, 'precio_unitario' => 150, 'vence_el' => '2026-11-25'],
                ['articulo_id' => $filtro->id, 'cantidad' => 2, 'precio_unitario' => 50],
            ],
        ])
        ->assertSessionHasNoErrors();

    actingAs(actorConRol('almacen'))
        ->get(route('almacen.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('porVencer', 1)
            ->where('porVencer.0.nombre', 'EXTINTOR PQS 6 KG')
            ->where('porVencer.0.vence_el', '2026-11-25')
            ->where('porVencer.0.vencido', false));
});
