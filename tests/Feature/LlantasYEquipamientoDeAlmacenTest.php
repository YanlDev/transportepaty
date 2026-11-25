<?php

use App\Enums\EstadoEquipamiento;
use App\Enums\EstadoOrdenTrabajo;
use App\Enums\OrigenLlanta;
use App\Models\Articulo;
use App\Models\CategoriaArticulo;
use App\Models\ConteoEquipamiento;
use App\Models\DetalleIngreso;
use App\Models\IngresoAlmacen;
use App\Models\ItemEquipamiento;
use App\Models\Llanta;
use App\Models\MovimientoEquipamiento;
use App\Models\OrdenTrabajo;
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
 * Las llantas y el equipamiento de las unidades salen del almacén: cada llanta
 * tiene su código desde la recepción y se monta con su vale, y lo que sale con vale a nombre de una unidad queda
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
 * La orden de trabajo en ejecución de la unidad, con esas llantas pedidas y
 * aprobadas: sin ella ninguna llanta sale del almacén.
 */
function ordenQuePideLlantas(Vehiculo $unidad, Articulo $articulo, float $cantidad = 1, EstadoOrdenTrabajo $estado = EstadoOrdenTrabajo::EnProceso): OrdenTrabajo
{
    $orden = OrdenTrabajo::factory()->create(['vehiculo_id' => $unidad->id, 'estado' => $estado, 'aprobado_at' => now()]);
    $orden->repuestos()->create(['articulo_id' => $articulo->id, 'descripcion' => $articulo->nombre, 'cantidad' => $cantidad, 'aprobado_at' => now()]);

    return $orden;
}

/** Una entrega del almacén a la orden, como la emite el almacén. */
function valeALaOrden(OrdenTrabajo $orden, Articulo $articulo, float $cantidad): SalidaAlmacen
{
    actingAs(actorConRol('admin'))
        ->post(route('almacen.salidas.store'), [
            'fecha' => '2026-11-05',
            'entregado_a' => 'Juan Quispe',
            'destino' => 'orden_trabajo',
            'orden_trabajo_id' => $orden->id,
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

/**
 * Las llantas de la recepción del artículo, ya con su código: quedan en el
 * almacén, listas para montar.
 *
 * @param  list<string>  $codigos
 * @return list<Llanta>
 */
function llantasCodificadas(Articulo $articulo, array $codigos): array
{
    $linea = DetalleIngreso::query()->where('articulo_id', $articulo->id)->latest('id')->firstOrFail();

    actingAs(actorConRol('taller'))
        ->post(route('mantenimiento.llantas.almacen.store'), ['detalle_ingreso_id' => $linea->id, 'condicion' => 'nueva', 'codigos' => $codigos])
        ->assertSessionHasNoErrors();

    return array_map(fn (string $codigo): Llanta => Llanta::query()->where('codigo', $codigo)->sole(), $codigos);
}

it('gives each tire of a reception its own code, and the reception is its lot', function (): void {
    $articulo = llantaDeAlmacen(3);
    $linea = DetalleIngreso::query()->where('articulo_id', $articulo->id)->sole();
    $filtro = Articulo::factory()->create();
    stockDe($filtro, 5);

    actingAs(actorConRol('taller'))
        ->get(route('mantenimiento.llantas.almacen'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('mantenimiento/llantas/almacen')
            ->has('lotes', 1)
            ->where('lotes.0.lineas.0.detalle_ingreso_id', $linea->id)
            ->where('lotes.0.lineas.0.cantidad', 3)
            ->where('lotes.0.lineas.0.por_codificar', 3));

    // Ni repetidos entre sí, ni de un artículo que no es llanta.
    actingAs(actorConRol('taller'))
        ->post(route('mantenimiento.llantas.almacen.store'), ['detalle_ingreso_id' => $linea->id, 'condicion' => 'nueva', 'codigos' => ['P-01', 'p-01']])
        ->assertSessionHasErrors('codigos.1');

    actingAs(actorConRol('taller'))
        ->post(route('mantenimiento.llantas.almacen.store'), [
            'detalle_ingreso_id' => DetalleIngreso::query()->where('articulo_id', $filtro->id)->sole()->id, 'condicion' => 'nueva', 'codigos' => ['P-09'],
        ])
        ->assertSessionHasErrors('detalle_ingreso_id');

    [$primera] = llantasCodificadas($articulo, ['P-01', 'P-02']);

    expect($primera->estaSinMontar())->toBeTrue()
        ->and($primera->detalle_ingreso_id)->toBe($linea->id)
        ->and($primera->articulo_id)->toBe($articulo->id)
        ->and($primera->marca)->toBe('Michelin')
        ->and((float) $primera->costo)->toBe(1450.0)
        ->and($primera->origen)->toBe(OrigenLlanta::Almacen);

    // El código es de una sola llanta; y no se codifican más de las que entraron.
    actingAs(actorConRol('taller'))
        ->post(route('mantenimiento.llantas.almacen.store'), ['detalle_ingreso_id' => $linea->id, 'condicion' => 'nueva', 'codigos' => ['p-02']])
        ->assertSessionHasErrors('codigos.0');

    actingAs(actorConRol('taller'))
        ->post(route('mantenimiento.llantas.almacen.store'), ['detalle_ingreso_id' => $linea->id, 'condicion' => 'nueva', 'codigos' => ['P-03', 'P-04']])
        ->assertSessionHasErrors('codigos');

    actingAs(actorConRol('taller'))
        ->get(route('mantenimiento.llantas.almacen'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('lotes.0.lineas.0.por_codificar', 1)
            ->has('lotes.0.llantas', 2)
            ->where('lotes.0.llantas.0.codigo', 'P-01')
            ->where('lotes.0.llantas.0.situacion', 'almacen'));

    // La que se codificó por error se quita mientras no haya salido.
    actingAs(actorConRol('taller'))
        ->delete(route('mantenimiento.llantas.almacen.destroy', $primera))
        ->assertSessionHasNoErrors();

    expect(Llanta::query()->pluck('codigo')->all())->toBe(['P-02']);
});

it('mounts a coded tire in one step: the warehouse vale is issued by itself', function (): void {
    $tracto = Vehiculo::factory()->create(['placa' => 'AAA-111']);
    $articulo = llantaDeAlmacen(2);
    [$llanta, $otra] = llantasCodificadas($articulo, ['P-01', 'P-02']);

    actingAs(actorConRol('taller'))
        ->get(route('mantenimiento.llantas.show', $tracto))
        ->assertInertia(fn (Assert $page) => $page
            ->has('enAlmacen', 2)
            ->where('enAlmacen.0.codigo', 'P-01')
            ->where('puedeCargaInicial', false));

    // Sin una orden de trabajo que la pida, la llanta no sale del almacén:
    // ni al montarla, ni con una entrega a nombre de la unidad.
    actingAs(actorConRol('taller'))
        ->post(route('mantenimiento.llantas.store', $tracto), montaje('1', ['origen' => 'almacen']))
        ->assertSessionHasErrors('llanta_id');

    actingAs(actorConRol('taller'))
        ->post(route('mantenimiento.llantas.store', $tracto), montaje('1', ['origen' => 'almacen', 'llanta_id' => $llanta->id]))
        ->assertSessionHasErrors('llanta_id');

    actingAs(actorConRol('admin'))
        ->post(route('almacen.salidas.store'), [
            'fecha' => '2026-11-05', 'entregado_a' => 'Juan Quispe', 'destino' => 'unidad', 'vehiculo_id' => $tracto->id,
            'detalles' => [['articulo_id' => $articulo->id, 'cantidad' => 1]],
        ])
        ->assertSessionHasErrors('detalles.0.articulo_id');

    expect(SalidaAlmacen::query()->count())->toBe(0);

    // Con la orden aprobada pero sin empezar (sin la foto del antes), tampoco.
    $orden = ordenQuePideLlantas($tracto, $articulo, 1, EstadoOrdenTrabajo::Pendiente);

    actingAs(actorConRol('taller'))
        ->post(route('mantenimiento.llantas.store', $tracto), montaje('1', ['origen' => 'almacen', 'llanta_id' => $llanta->id]))
        ->assertSessionHasErrors('llanta_id');

    $orden->update(['estado' => EstadoOrdenTrabajo::EnProceso]);

    actingAs(actorConRol('taller'))
        ->post(route('mantenimiento.llantas.store', $tracto), montaje('1', ['origen' => 'almacen', 'llanta_id' => $llanta->id, 'entregado_a' => 'Juan Quispe', 'kilometraje' => 100000]))
        ->assertSessionHasNoErrors();

    $llanta->refresh();
    $vale = SalidaAlmacen::query()->sole();

    expect($llanta->estaMontada())->toBeTrue()
        ->and($llanta->vehiculo_id)->toBe($tracto->id)
        ->and($llanta->posicion)->toBe('1')
        ->and($llanta->detalle_salida_id)->toBe($vale->detalles()->sole()->id)
        ->and($vale->vehiculo_id)->toBe($tracto->id)
        ->and($vale->orden_trabajo_id)->toBe($orden->id)
        ->and($vale->entregado_a)->toBe('Juan Quispe')
        ->and((float) $articulo->refresh()->stock)->toBe(1.0);

    // Ya montada, no se monta de nuevo ni en otra unidad.
    actingAs(actorConRol('taller'))
        ->post(route('mantenimiento.llantas.store', Vehiculo::factory()->create()), montaje('1', ['origen' => 'almacen', 'llanta_id' => $llanta->id, 'entregado_a' => 'Juan Quispe']))
        ->assertSessionHasErrors('llanta_id');

    // La ficha la encuentra por su código y dice dónde está y cuánto rodó.
    actingAs(actorConRol('taller'))
        ->post(route('mantenimiento.llantas.store', $tracto), ['tipo' => 'retiro', 'posicion' => '1', 'fecha' => '2026-11-08', 'motivo_retiro' => 'otro', 'kilometraje' => 112500])
        ->assertSessionHasNoErrors();

    actingAs(actorConRol('taller'))
        ->get(route('mantenimiento.llantas.buscar', ['codigo' => 'p-01']))
        ->assertRedirect(route('mantenimiento.llantas.ficha', $llanta));

    actingAs(actorConRol('taller'))
        ->get(route('mantenimiento.llantas.ficha', $llanta))
        ->assertInertia(fn (Assert $page) => $page
            ->component('mantenimiento/llantas/ficha')
            ->where('llanta.codigo', 'P-01')
            ->where('llanta.situacion', 'retirada')
            ->where('llanta.km_recorridos', 12500)
            ->where('llanta.km_incompleto', false)
            ->where('llanta.lote.numero', IngresoAlmacen::query()->sole()->numero())
            ->where('llanta.vale', $vale->numero())
            ->has('movimientos', 2));

    actingAs(actorConRol('taller'))
        ->get(route('mantenimiento.llantas.buscar', ['codigo' => 'P-0']))
        ->assertInertia(fn (Assert $page) => $page->component('mantenimiento/llantas/buscar')->has('llantas', 2));

    expect($otra->refresh()->estaSinMontar())->toBeTrue();
});

it('uses the vale the orden already has before issuing another one', function (): void {
    $tracto = Vehiculo::factory()->create();
    $otro = Vehiculo::factory()->create();
    $articulo = llantaDeAlmacen(3);
    [$primera, $segunda, $tercera] = llantasCodificadas($articulo, ['P-01', 'P-02', 'P-03']);
    $orden = ordenQuePideLlantas($tracto, $articulo, 2);
    ordenQuePideLlantas($otro, $articulo, 1);
    $linea = valeALaOrden($orden, $articulo, 1)->detalles()->sole();

    actingAs(actorConRol('taller'))
        ->post(route('mantenimiento.llantas.store', $tracto), montaje('1', ['origen' => 'almacen', 'llanta_id' => $primera->id]))
        ->assertSessionHasNoErrors();

    expect($primera->refresh()->detalle_salida_id)->toBe($linea->id)
        ->and(SalidaAlmacen::query()->count())->toBe(1);

    // La línea ya no tiene llantas por montar: la segunda saca su propio vale.
    actingAs(actorConRol('taller'))
        ->post(route('mantenimiento.llantas.store', $tracto), montaje('2', ['origen' => 'almacen', 'llanta_id' => $segunda->id, 'entregado_a' => 'Juan Quispe']))
        ->assertSessionHasNoErrors();

    expect(SalidaAlmacen::query()->count())->toBe(2);

    // Se cargó por error: la instalación se elimina y la llanta no se pierde.
    actingAs(actorConRol('taller'))
        ->delete(route('mantenimiento.llantas.movimientos.destroy', $segunda->movimientos()->sole()))
        ->assertSessionHasNoErrors();

    $segunda->refresh();

    expect($segunda->estaSinMontar())->toBeTrue()
        ->and($segunda->detalle_salida_id)->not->toBeNull()
        ->and($segunda->movimientos()->count())->toBe(0);

    // Entregada a esta unidad, no se monta en otra; anulado su vale, vuelve al almacén.
    actingAs(actorConRol('taller'))
        ->post(route('mantenimiento.llantas.store', $otro), montaje('1', ['origen' => 'almacen', 'llanta_id' => $segunda->id, 'entregado_a' => 'Juan Quispe']))
        ->assertSessionHasErrors('llanta_id');

    actingAs(actorConRol('admin'))
        ->post(route('almacen.salidas.anular', $segunda->detalleSalida->salida), ['motivo' => 'Se emitió por error'])
        ->assertSessionHasNoErrors();

    expect($segunda->refresh()->detalle_salida_id)->toBeNull()
        ->and((float) $articulo->refresh()->stock)->toBe(2.0)
        ->and($tercera->refresh()->estaSinMontar())->toBeTrue();
});

it('charges the tire to the orden that asked for it, and shows what there is to do', function (): void {
    $tracto = Vehiculo::factory()->create();
    $articulo = llantaDeAlmacen(2);
    [$pedida, $suelta] = llantasCodificadas($articulo, ['P-01', 'P-02']);
    $orden = ordenQuePideLlantas($tracto, $articulo, 1);

    actingAs(actorConRol('taller'))
        ->get(route('mantenimiento.llantas.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('resumen.sin_codigo', 0)
            ->where('resumen.en_almacen', 2)
            ->where('resumen.entregadas', 0));

    actingAs(actorConRol('taller'))
        ->get(route('mantenimiento.llantas.show', $tracto))
        ->assertInertia(fn (Assert $page) => $page->where('orden.numero', $orden->numero()));

    actingAs(actorConRol('taller'))
        ->post(route('mantenimiento.llantas.store', $tracto), montaje('1', ['origen' => 'almacen', 'llanta_id' => $pedida->id]))
        ->assertSessionHasNoErrors();

    // La orden pidió una: la segunda no sale sin pedirla como adicional.
    actingAs(actorConRol('taller'))
        ->post(route('mantenimiento.llantas.store', $tracto), montaje('2', ['origen' => 'almacen', 'llanta_id' => $suelta->id]))
        ->assertSessionHasErrors('llanta_id');

    expect($pedida->refresh()->detalleSalida->salida->orden_trabajo_id)->toBe($orden->id)
        ->and($pedida->detalleSalida->salida->destino->value)->toBe('orden_trabajo')
        ->and($suelta->refresh()->estaSinMontar())->toBeTrue()
        ->and(SalidaAlmacen::query()->count())->toBe(1);
});

it('lets the mechanic change the tires of the unit only while he works its orden, with what the orden asked for', function (): void {
    $tracto = Vehiculo::factory()->create();
    $articulo = llantaDeAlmacen(2);
    [$pedida, $otra] = llantasCodificadas($articulo, ['P-01', 'P-02']);
    $mecanico = actorConRol('mecanico');
    $ajeno = actorConRol('mecanico');
    $orden = OrdenTrabajo::factory()->create(['vehiculo_id' => $tracto->id, 'estado' => EstadoOrdenTrabajo::Pendiente, 'aprobado_at' => now()]);
    $orden->mecanicos()->attach($mecanico->id, ['responsable' => true]);
    $orden->repuestos()->create(['articulo_id' => $articulo->id, 'descripcion' => $articulo->nombre, 'cantidad' => 1, 'aprobado_at' => now()]);
    $monta = fn (string $posicion, Llanta $llanta): array => montaje($posicion, ['origen' => 'almacen', 'llanta_id' => $llanta->id]);

    // Asignado, ve las llantas; sin empezar la orden (sin la foto del antes) no las toca.
    actingAs($mecanico)->get(route('mantenimiento.llantas.show', $tracto))
        ->assertInertia(fn (Assert $page) => $page->where('puedeRegistrar', false)->where('puedeCorregir', false)->where('orden.numero', $orden->numero()));
    actingAs($ajeno)->get(route('mantenimiento.llantas.show', $tracto))->assertForbidden();
    actingAs($mecanico)->post(route('mantenimiento.llantas.store', $tracto), $monta('1', $pedida))->assertForbidden();

    $orden->update(['estado' => EstadoOrdenTrabajo::EnProceso]);

    actingAs($ajeno)->post(route('mantenimiento.llantas.store', $tracto), $monta('1', $pedida))->assertForbidden();
    actingAs($mecanico)->post(route('mantenimiento.llantas.store', $tracto), $monta('1', $pedida))->assertSessionHasNoErrors();

    $vale = $pedida->refresh()->detalleSalida->salida;

    expect($pedida->estaMontada())->toBeTrue()
        ->and($vale->orden_trabajo_id)->toBe($orden->id)
        ->and($vale->entregado_a)->toBe($mecanico->name);

    // La orden pidió una sola: la segunda no sale del almacén sin pedirla.
    actingAs($mecanico)->post(route('mantenimiento.llantas.store', $tracto), $monta('2', $otra))->assertSessionHasErrors('llanta_id');

    expect($otra->refresh()->estaSinMontar())->toBeTrue()
        ->and(SalidaAlmacen::query()->count())->toBe(1);

    // Retirar la que cambia sí puede; corregir el historial, no.
    actingAs($mecanico)
        ->post(route('mantenimiento.llantas.store', $tracto), ['tipo' => 'retiro', 'posicion' => '1', 'fecha' => '2026-11-08', 'motivo_retiro' => 'desgaste'])
        ->assertSessionHasNoErrors();
    actingAs($mecanico)
        ->delete(route('mantenimiento.llantas.movimientos.destroy', $pedida->movimientos()->latest('id')->firstOrFail()))
        ->assertForbidden();
});

it('does not return or void in the warehouse a tire that is already mounted', function (): void {
    $tracto = Vehiculo::factory()->create();
    $articulo = llantaDeAlmacen();
    [$llanta] = llantasCodificadas($articulo, ['P-01', 'P-02']);
    $vale = valeALaOrden(ordenQuePideLlantas($tracto, $articulo, 2), $articulo, 2);
    $linea = $vale->detalles()->sole();

    actingAs(actorConRol('taller'))
        ->post(route('mantenimiento.llantas.store', $tracto), montaje('1', ['origen' => 'almacen', 'llanta_id' => $llanta->id]))
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

    // Una recepción con llantas ya codificadas no se anula.
    actingAs(actorConRol('admin'))
        ->post(route('almacen.ingresos.anular', IngresoAlmacen::query()->where('tipo', 'compra')->sole()), ['motivo' => 'Se registró por error'])
        ->assertSessionHasErrors('motivo');
});

it('moves a tire from one unit to another without the warehouse, keeping its history', function (): void {
    $tracto = Vehiculo::factory()->create(['placa' => 'AAA-111']);
    $carreta = Vehiculo::factory()->carreta()->create(['placa' => 'BBB-222']);
    $articulo = llantaDeAlmacen(1);
    [$llanta] = llantasCodificadas($articulo, ['P-01']);
    ordenQuePideLlantas($tracto, $articulo);

    actingAs(actorConRol('taller'))
        ->post(route('mantenimiento.llantas.store', $tracto), montaje('1', ['origen' => 'almacen', 'llanta_id' => $llanta->id, 'entregado_a' => 'Juan Quispe']))
        ->assertSessionHasNoErrors();

    $linea = SalidaAlmacen::query()->sole()->detalles()->sole();

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

it('lets only the admin load the tires that were already mounted before the warehouse, each with its code', function (): void {
    $tracto = Vehiculo::factory()->create();
    $previa = ['origen' => 'previa', 'marca' => 'Goodyear', 'medida' => '295/80R22.5', 'condicion' => 'usada', 'fecha' => '2026-11-01'];

    // Sin vale, taller no puede: cargar una que «ya estaba» es de quien tiene ese permiso.
    actingAs(actorConRol('taller'))
        ->post(route('mantenimiento.llantas.store', $tracto), montaje('1', [...$previa, 'codigo' => 'F-01']))
        ->assertSessionHasErrors('origen');

    actingAs(actorConRol('admin'))
        ->post(route('mantenimiento.llantas.store', $tracto), montaje('1', $previa))
        ->assertSessionHasErrors('codigo');

    actingAs(actorConRol('admin'))
        ->post(route('mantenimiento.llantas.store', $tracto), montaje('1', [...$previa, 'codigo' => 'F-01']))
        ->assertSessionHasNoErrors();

    // El mismo código no va en otra llanta.
    actingAs(actorConRol('admin'))
        ->post(route('mantenimiento.llantas.store', $tracto), montaje('2', [...$previa, 'codigo' => 'f-01']))
        ->assertSessionHasErrors('codigo');

    $llanta = Llanta::query()->sole();

    expect($llanta->origen)->toBe(OrigenLlanta::Previa)
        ->and($llanta->detalle_salida_id)->toBeNull()
        ->and($llanta->codigo)->toBe('F-01')
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
    expect(app(ArticulosDeEquipamiento::class)->asegurar())->toBe(15)
        ->and(app(ArticulosDeEquipamiento::class)->asegurar())->toBe(0);

    $extintor = itemDeEquipo('Extintor')->articulo;

    expect($extintor->nombre)->toBe('EXTINTOR')
        ->and($extintor->categoria->clave)->toBe('equipamiento')
        ->and(Articulo::query()->where('categoria_articulo_id', $extintor->categoria_articulo_id)->count())->toBe(15);

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
