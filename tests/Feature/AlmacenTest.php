<?php

use App\Enums\DestinoSalida;
use App\Enums\EstadoInventario;
use App\Enums\TipoMovimientoAlmacen;
use App\Models\Articulo;
use App\Models\IngresoAlmacen;
use App\Models\InventarioAlmacen;
use App\Models\MovimientoAlmacen;
use App\Models\OrdenTrabajo;
use App\Models\Proveedor;
use App\Models\SalidaAlmacen;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    Storage::fake('public');
});

function almacenero(): User
{
    return actorConRol('almacen');
}

/**
 * Una compra con factura, precios sin IGV, registrada por el almacén.
 *
 * @param  list<array{0: Articulo, 1: float, 2: float}>  $lineas  artículo, cantidad, precio
 */
function comprar(array $lineas, string $fecha = '2026-11-02', array $extra = []): IngresoAlmacen
{
    actingAs(almacenero())
        ->post(route('almacen.ingresos.store'), [
            'fecha' => $fecha,
            'proveedor_id' => Proveedor::factory()->create()->id,
            'tipo_comprobante' => 'factura',
            'serie_numero' => 'F001-'.fake()->unique()->numerify('#####'),
            'moneda' => 'PEN',
            'precios_con_igv' => false,
            'detalles' => array_map(fn (array $linea): array => [
                'articulo_id' => $linea[0]->id,
                'cantidad' => $linea[1],
                'precio_unitario' => $linea[2],
            ], $lineas),
            ...$extra,
        ])
        ->assertSessionHasNoErrors();

    return IngresoAlmacen::query()->latest('id')->firstOrFail();
}

/**
 * Un vale de salida a una unidad.
 *
 * @param  list<array{0: Articulo, 1: float}>  $lineas
 */
function despachar(array $lineas, string $fecha = '2026-11-05', ?Vehiculo $vehiculo = null): SalidaAlmacen
{
    actingAs(almacenero())
        ->post(route('almacen.salidas.store'), [
            'fecha' => $fecha,
            'destino' => 'unidad',
            'vehiculo_id' => ($vehiculo ?? Vehiculo::factory()->create())->id,
            'entregado_a' => 'Técnico',
            'detalles' => array_map(fn (array $linea): array => ['articulo_id' => $linea[0]->id, 'cantidad' => $linea[1]], $lineas),
        ])
        ->assertSessionHasNoErrors();

    return SalidaAlmacen::query()->latest('id')->firstOrFail();
}

// ─── Valorización ───────────────────────────────────────────────────────────

/**
 * 10 a S/ 10 y 10 a S/ 20 dejan un promedio de S/ 15; los 5 que salen se
 * llevan S/ 75 y quedan 15 que valen S/ 225.
 */
it('values the stock at the weighted average cost', function (): void {
    $filtro = Articulo::factory()->create();

    comprar([[$filtro, 10, 10]], '2026-11-01');
    comprar([[$filtro, 10, 20]], '2026-11-02');
    $vale = despachar([[$filtro, 5]]);

    $filtro->refresh();
    expect((float) $filtro->stock)->toBe(15.0)
        ->and((float) $filtro->costo_promedio)->toBe(15.0)
        ->and((float) $filtro->valor)->toBe(225.0)
        ->and((float) $vale->refresh()->costo_total)->toBe(75.0)
        ->and((float) $vale->detalles->first()->costo_unitario)->toBe(15.0);

    $ultimo = MovimientoAlmacen::query()->latest('id')->first();
    expect($ultimo->tipo)->toBe(TipoMovimientoAlmacen::Salida)
        ->and((float) $ultimo->saldo_cantidad)->toBe(15.0)
        ->and((float) $ultimo->saldo_valor)->toBe(225.0);
});

/**
 * La factura con precios con IGV entra sin IGV: el IGV es crédito fiscal.
 * La boleta, en cambio, entra con el IGV, porque no se recupera.
 */
it('takes the IGV out of a factura but keeps it in a boleta', function (): void {
    $filtro = Articulo::factory()->create();
    $otro = Articulo::factory()->create();

    $factura = comprar([[$filtro, 2, 118]], extra: ['precios_con_igv' => true]);
    $boleta = comprar([[$otro, 1, 100]], extra: ['tipo_comprobante' => 'boleta', 'precios_con_igv' => false]);

    expect((float) $factura->total)->toBe(236.0)
        ->and((float) $factura->subtotal)->toBe(200.0)
        ->and((float) $factura->igv)->toBe(36.0)
        ->and((float) $factura->costo_total)->toBe(200.0)
        ->and((float) $filtro->refresh()->costo_promedio)->toBe(100.0)
        ->and((float) $boleta->costo_total)->toBe(118.0)
        ->and((float) $otro->refresh()->costo_promedio)->toBe(118.0);
});

it('converts a purchase in dollars to soles at the exchange rate of the invoice', function (): void {
    $bateria = Articulo::factory()->create();

    $compra = comprar([[$bateria, 2, 100]], extra: ['moneda' => 'USD', 'tipo_cambio' => 3.75]);

    expect((float) $compra->total)->toBe(236.0)
        ->and((float) $compra->costo_total)->toBe(750.0)
        ->and((float) $bateria->refresh()->costo_promedio)->toBe(375.0);
});

/**
 * El aceite se compra por cilindro y sale por litro: dos cilindros son 416
 * litros, y el litro cuesta lo que el cilindro entre 208.
 */
it('stores what is bought by the drum in liters', function (): void {
    $aceite = Articulo::factory()->aceiteEnCilindro()->create();

    comprar([[$aceite, 2, 2080]]);
    despachar([[$aceite, 30]]);

    $aceite->refresh();
    expect((float) $aceite->stock)->toBe(386.0)
        ->and((float) $aceite->costo_promedio)->toBe(10.0)
        ->and((float) $aceite->valor)->toBe(3860.0);
});

/**
 * Una factura que llega tarde, con fecha anterior a un vale ya emitido,
 * cambia el promedio de esa fecha: el vale se revaloriza solo.
 */
it('revalues later issues when a purchase is registered with an earlier date', function (): void {
    $filtro = Articulo::factory()->create();

    comprar([[$filtro, 10, 10]], '2026-11-01');
    $vale = despachar([[$filtro, 10]], '2026-11-05');
    expect((float) $vale->refresh()->costo_total)->toBe(100.0);

    comprar([[$filtro, 10, 30]], '2026-11-03');

    // Al 05/11 había 20 a S/ 20 de promedio: los 10 del vale valen S/ 200.
    expect((float) $vale->refresh()->costo_total)->toBe(200.0)
        ->and((float) $filtro->refresh()->stock)->toBe(10.0)
        ->and((float) $filtro->valor)->toBe(200.0);
});

// ─── Lo que no se deja hacer ────────────────────────────────────────────────

it('does not issue more than there is in stock', function (): void {
    $filtro = Articulo::factory()->create();
    comprar([[$filtro, 3, 10]]);

    actingAs(almacenero())
        ->post(route('almacen.salidas.store'), [
            'fecha' => '2026-11-05',
            'destino' => 'consumo',
            'detalles' => [['articulo_id' => $filtro->id, 'cantidad' => 5]],
        ])
        ->assertSessionHasErrors('stock');

    expect(SalidaAlmacen::query()->count())->toBe(0)
        ->and((float) $filtro->refresh()->stock)->toBe(3.0);
});

it('does not issue on a date when the stock had not arrived yet', function (): void {
    $filtro = Articulo::factory()->create();
    comprar([[$filtro, 3, 10]], '2026-11-05');

    actingAs(almacenero())
        ->post(route('almacen.salidas.store'), [
            'fecha' => '2026-11-04',
            'destino' => 'consumo',
            'detalles' => [['articulo_id' => $filtro->id, 'cantidad' => 1]],
        ])
        ->assertSessionHasErrors('stock');
});

it('rejects the same invoice from the same supplier twice', function (): void {
    $filtro = Articulo::factory()->create();
    $compra = comprar([[$filtro, 1, 10]]);

    actingAs(almacenero())
        ->post(route('almacen.ingresos.store'), [
            'fecha' => '2026-11-03',
            'proveedor_id' => $compra->proveedor_id,
            'tipo_comprobante' => 'factura',
            'serie_numero' => strtolower($compra->serie_numero),
            'moneda' => 'PEN',
            'detalles' => [['articulo_id' => $filtro->id, 'cantidad' => 1, 'precio_unitario' => 10]],
        ])
        ->assertSessionHasErrors('serie_numero');
});

it('asks for the exchange rate when the invoice is in dollars', function (): void {
    actingAs(almacenero())
        ->post(route('almacen.ingresos.store'), [
            'fecha' => '2026-11-03',
            'proveedor_id' => Proveedor::factory()->create()->id,
            'tipo_comprobante' => 'factura',
            'serie_numero' => 'F001-1',
            'moneda' => 'USD',
            'detalles' => [['articulo_id' => Articulo::factory()->create()->id, 'cantidad' => 1, 'precio_unitario' => 10]],
        ])
        ->assertSessionHasErrors('tipo_cambio');
});

// ─── Anular y devolver ──────────────────────────────────────────────────────

it('voids an issue and puts the stock back', function (): void {
    $filtro = Articulo::factory()->create();
    comprar([[$filtro, 10, 10]]);
    $vale = despachar([[$filtro, 4]]);

    actingAs(almacenero())
        ->post(route('almacen.salidas.anular', $vale), ['motivo' => 'Se registró dos veces'])
        ->assertSessionHasNoErrors();

    expect($vale->refresh()->estaAnulado())->toBeTrue()
        ->and($vale->motivo_anulacion)->toBe('Se registró dos veces')
        ->and((float) $filtro->refresh()->stock)->toBe(10.0)
        ->and(MovimientoAlmacen::query()->where('tipo', 'salida')->count())->toBe(0);
});

it('does not void a purchase whose stock was already issued', function (): void {
    $filtro = Articulo::factory()->create();
    $compra = comprar([[$filtro, 5, 10]]);
    despachar([[$filtro, 4]]);

    actingAs(almacenero())
        ->post(route('almacen.ingresos.anular', $compra), ['motivo' => 'Factura equivocada'])
        ->assertSessionHasErrors('stock');

    expect($compra->refresh()->estaAnulado())->toBeFalse()
        ->and((float) $filtro->refresh()->stock)->toBe(1.0);
});

it('takes back what was not used at the cost it left with', function (): void {
    $filtro = Articulo::factory()->create();
    comprar([[$filtro, 10, 10]], '2026-11-01');
    comprar([[$filtro, 10, 20]], '2026-11-02');
    $vale = despachar([[$filtro, 4]]);
    $linea = $vale->detalles->first();

    actingAs(almacenero())
        ->post(route('almacen.salidas.devolver', $vale), [
            'fecha' => '2026-11-06',
            'detalles' => [['detalle_salida_id' => $linea->id, 'cantidad' => 1]],
        ])
        ->assertSessionHasNoErrors();

    $devolucion = IngresoAlmacen::query()->where('tipo', 'devolucion')->sole();
    expect((float) $devolucion->costo_total)->toBe(15.0)
        ->and((float) $filtro->refresh()->stock)->toBe(17.0)
        ->and($linea->cantidadDevuelta())->toBe(1.0);

    // No puede volver más de lo que salió.
    actingAs(almacenero())
        ->post(route('almacen.salidas.devolver', $vale), [
            'fecha' => '2026-11-06',
            'detalles' => [['detalle_salida_id' => $linea->id, 'cantidad' => 4]],
        ])
        ->assertSessionHasErrors('detalles.0.cantidad');
});

// ─── Conteos ────────────────────────────────────────────────────────────────

/**
 * No hay Excel de partida: el primer conteo es el saldo inicial, al costo
 * que se anote. El siguiente, si falta algo, lo saca al promedio.
 */
it('starts the stock from the first count and adjusts it with the next ones', function (): void {
    $filtro = Articulo::factory()->create();
    $usuario = almacenero();

    actingAs($usuario)->post(route('almacen.inventarios.store'), ['fecha' => '2026-11-01'])->assertSessionHasNoErrors();
    $conteo = InventarioAlmacen::query()->sole();

    actingAs($usuario)
        ->post(route('almacen.inventarios.lineas.store', $conteo), [
            'articulo_id' => $filtro->id,
            'cantidad_contada' => 12,
            'costo_unitario' => 25,
            'costo_estimado' => true,
        ])
        ->assertSessionHasNoErrors();

    // Abierto, todavía no toca el stock.
    expect((float) $filtro->refresh()->stock)->toBe(0.0);

    actingAs($usuario)->post(route('almacen.inventarios.cerrar', $conteo))->assertSessionHasNoErrors();

    $inicial = MovimientoAlmacen::query()->sole();
    expect($conteo->refresh()->estado)->toBe(EstadoInventario::Cerrado)
        ->and($inicial->tipo)->toBe(TipoMovimientoAlmacen::SaldoInicial)
        ->and((float) $filtro->refresh()->stock)->toBe(12.0)
        ->and((float) $filtro->valor)->toBe(300.0);

    actingAs($usuario)->post(route('almacen.inventarios.store'), ['fecha' => '2026-11-05']);
    $segundo = InventarioAlmacen::query()->latest('id')->first();
    actingAs($usuario)->post(route('almacen.inventarios.lineas.store', $segundo), ['articulo_id' => $filtro->id, 'cantidad_contada' => 10]);
    actingAs($usuario)->post(route('almacen.inventarios.cerrar', $segundo))->assertSessionHasNoErrors();

    $faltante = MovimientoAlmacen::query()->latest('id')->first();
    expect($faltante->tipo)->toBe(TipoMovimientoAlmacen::AjusteSalida)
        ->and((float) $faltante->salida)->toBe(2.0)
        ->and((float) $faltante->costo_total)->toBe(50.0)
        ->and((float) $filtro->refresh()->stock)->toBe(10.0)
        ->and((float) $segundo->detalles->first()->diferencia)->toBe(-2.0);

    // Anular el conteo cerrado saca su ajuste del kardex.
    actingAs($usuario)->post(route('almacen.inventarios.anular', $segundo), ['motivo' => 'Se contó mal el estante'])->assertSessionHasNoErrors();
    expect((float) $filtro->refresh()->stock)->toBe(12.0);
});

it('does not close an empty count', function (): void {
    $conteo = InventarioAlmacen::factory()->create();

    actingAs(almacenero())->post(route('almacen.inventarios.cerrar', $conteo))->assertSessionHasErrors('inventario');

    expect($conteo->refresh()->estado)->toBe(EstadoInventario::Abierto);
});

// ─── Destinos ───────────────────────────────────────────────────────────────

it('charges an issue to the unit of its work order', function (): void {
    $filtro = Articulo::factory()->create();
    comprar([[$filtro, 5, 10]]);
    $orden = OrdenTrabajo::factory()->create();

    actingAs(almacenero())
        ->post(route('almacen.salidas.store'), [
            'fecha' => '2026-11-05',
            'destino' => 'orden_trabajo',
            'orden_trabajo_id' => $orden->id,
            'detalles' => [['articulo_id' => $filtro->id, 'cantidad' => 2]],
        ])
        ->assertSessionHasNoErrors();

    $vale = SalidaAlmacen::query()->sole();
    expect($vale->destino)->toBe(DestinoSalida::OrdenTrabajo)
        ->and($vale->vehiculo_id)->toBe($orden->vehiculo_id)
        ->and(MovimientoAlmacen::query()->where('tipo', 'salida')->sole()->orden_trabajo_id)->toBe($orden->id);
});

it('requires a unit when the issue goes to a unit', function (): void {
    actingAs(almacenero())
        ->post(route('almacen.salidas.store'), [
            'fecha' => '2026-11-05',
            'destino' => 'unidad',
            'detalles' => [['articulo_id' => Articulo::factory()->create()->id, 'cantidad' => 1]],
        ])
        ->assertSessionHasErrors('vehiculo_id');
});

// ─── Catálogo ───────────────────────────────────────────────────────────────

it('gives a new item the next code when none is written', function (): void {
    actingAs(almacenero())
        ->post(route('almacen.articulos.store'), [
            'nombre' => 'filtro de petróleo',
            'unidad' => 'und',
            'marcas_compatibles' => ['scania', 'SCANIA', 'volvo'],
        ])
        ->assertSessionHasNoErrors();

    $articulo = Articulo::query()->sole();
    expect($articulo->codigo)->toBe('A-00001')
        ->and($articulo->nombre)->toBe('FILTRO DE PETRÓLEO')
        ->and($articulo->marcas_compatibles)->toBe(['SCANIA', 'VOLVO']);
});

it('does not change the unit of an item that already has movements', function (): void {
    $filtro = Articulo::factory()->create();
    comprar([[$filtro, 1, 10]]);

    actingAs(almacenero())
        ->put(route('almacen.articulos.update', $filtro), ['nombre' => $filtro->nombre, 'unidad' => 'jgo'])
        ->assertSessionHasErrors('unidad');
});

it('registers a supplier and does not repeat its RUC', function (): void {
    actingAs(almacenero())
        ->post(route('almacen.proveedores.store'), ['ruc' => '20100136741', 'razon_social' => 'repuestos andinos sac'])
        ->assertSessionHasNoErrors();

    expect(Proveedor::query()->sole()->razon_social)->toBe('REPUESTOS ANDINOS SAC');

    actingAs(almacenero())
        ->post(route('almacen.proveedores.store'), ['ruc' => '20100136741', 'razon_social' => 'Otro'])
        ->assertSessionHasErrors('ruc');
});

it('keeps the scanned invoice with the purchase', function (): void {
    $filtro = Articulo::factory()->create();

    $compra = comprar([[$filtro, 1, 10]], extra: ['comprobante' => UploadedFile::fake()->create('factura.pdf', 50, 'application/pdf')]);

    expect($compra->getFirstMedia('comprobante'))->not->toBeNull();

    actingAs(almacenero())->get(route('almacen.ingresos.comprobante', $compra))->assertOk();
});

// ─── Quién ve qué ───────────────────────────────────────────────────────────

/**
 * El taller ve el stock para saber qué hay, pero no cuánto vale ni puede
 * registrar compras.
 */
it('shows the stock to the workshop without the costs', function (): void {
    $filtro = Articulo::factory()->create();
    comprar([[$filtro, 4, 10]]);

    actingAs(actorConRol('taller'))
        ->get(route('almacen.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('almacen/index')
            ->where('articulos.data.0.stock', 4)
            ->where('articulos.data.0.valor', null)
            ->where('resumen.valor', null));

    actingAs(actorConRol('taller'))->get(route('almacen.ingresos.create'))->assertForbidden();
    actingAs(actorConRol('taller'))->get(route('almacen.articulos.kardex', $filtro))->assertForbidden();
});

it('shows the costs to the warehouse', function (): void {
    $filtro = Articulo::factory()->create();
    comprar([[$filtro, 4, 10]]);

    actingAs(almacenero())
        ->get(route('almacen.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('articulos.data.0.valor', 40)
            ->where('resumen.valor', 40));
});

it('keeps the warehouse closed to who has no access', function (): void {
    actingAs(actorConRol('contador'))->get(route('almacen.index'))->assertForbidden();
    actingAs(actorConRol('contador'))->get(route('almacen.salidas.index'))->assertForbidden();
});

it('opens every screen of the warehouse', function (): void {
    $filtro = Articulo::factory()->create();
    $compra = comprar([[$filtro, 5, 10]]);
    $vale = despachar([[$filtro, 1]]);
    $conteo = InventarioAlmacen::factory()->create();
    $usuario = actorConRol('admin');

    foreach ([
        route('almacen.index'),
        route('almacen.articulos.create'),
        route('almacen.articulos.show', $filtro),
        route('almacen.articulos.edit', $filtro),
        route('almacen.proveedores.index'),
        route('almacen.ingresos.index'),
        route('almacen.ingresos.create'),
        route('almacen.ingresos.show', $compra),
        route('almacen.salidas.index'),
        route('almacen.salidas.create'),
        route('almacen.salidas.show', $vale),
        route('almacen.inventarios.index'),
        route('almacen.inventarios.show', $conteo),
    ] as $url) {
        actingAs($usuario)->get($url)->assertOk();
    }
});

it('downloads the stock and the kardex in Excel', function (): void {
    $filtro = Articulo::factory()->create();
    comprar([[$filtro, 5, 10]]);
    despachar([[$filtro, 2]]);

    actingAs(almacenero())->get(route('almacen.exportar'))->assertOk()->assertDownload();
    actingAs(almacenero())->get(route('almacen.articulos.kardex', $filtro))->assertOk()->assertDownload();
    actingAs(almacenero())->get(route('almacen.kardex.exportar', ['desde' => '2026-11-01', 'hasta' => '2026-11-30']))->assertOk()->assertDownload();
});
