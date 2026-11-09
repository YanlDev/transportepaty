<?php

use App\Enums\DestinoSalida;
use App\Enums\EstadoInventario;
use App\Enums\Moneda;
use App\Enums\TipoComprobante;
use App\Enums\TipoMovimientoAlmacen;
use App\Models\Articulo;
use App\Models\CategoriaArticulo;
use App\Models\IngresoAlmacen;
use App\Models\InventarioAlmacen;
use App\Models\MovimientoAlmacen;
use App\Models\OrdenTrabajo;
use App\Models\Proveedor;
use App\Models\SalidaAlmacen;
use App\Models\UbicacionAlmacen;
use App\Models\User;
use App\Models\Vehiculo;
use App\Services\CostoDeCompra;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

/*
 * El reloj fijo en noviembre de 2026 (mediodía en Lima): las fechas de estas
 * pruebas son de ese mes, que es el único abierto. Octubre ya está cerrado.
 */
beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-11-09 17:00:00'));
    Storage::fake('public');
    Storage::fake('local');
});

function almacenero(): User
{
    return actorConRol('almacen');
}

/** Un PDF de verdad (con contenido), para que lo acepte la validación. */
function pdfFalso(string $nombre = 'factura.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($nombre, "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n");
}

/**
 * Los datos de una compra con factura, precios sin IGV, con su PDF. El total
 * del comprobante sale de los ítems, salvo que la prueba diga otro.
 *
 * @param  list<array{0: Articulo, 1: float, 2: float, 3?: bool}>  $lineas  artículo, cantidad, precio, bonificación
 * @return array<string, mixed>
 */
function datosDeCompra(array $lineas, string $fecha = '2026-11-02', array $extra = []): array
{
    $datos = [
        'fecha' => $fecha,
        'fecha_emision' => $fecha,
        'proveedor_id' => Proveedor::factory()->create()->id,
        'tipo_comprobante' => 'factura',
        'serie_numero' => 'F001-'.fake()->unique()->numerify('#####'),
        'moneda' => 'PEN',
        'precios_con_igv' => false,
        'comprobante' => pdfFalso(),
        'detalles' => array_map(fn (array $linea): array => [
            'articulo_id' => $linea[0]->id,
            'cantidad' => $linea[1],
            'precio_unitario' => $linea[2],
            'bonificacion' => $linea[3] ?? false,
        ], $lineas),
        ...$extra,
    ];

    $datos['total_comprobante'] ??= app(CostoDeCompra::class)->calcular(
        TipoComprobante::from($datos['tipo_comprobante']),
        Moneda::from($datos['moneda']),
        isset($datos['tipo_cambio']) ? (float) $datos['tipo_cambio'] : null,
        (bool) $datos['precios_con_igv'],
        array_map(fn (array $linea): array => [
            'cantidad' => (float) $linea['cantidad'],
            'precio_unitario' => (float) $linea['precio_unitario'],
            'factor' => 1.0,
            'bonificacion' => (bool) $linea['bonificacion'],
        ], $datos['detalles']),
        (float) ($datos['descuento'] ?? 0),
        (float) ($datos['flete'] ?? 0),
    )['total'];

    return $datos;
}

/**
 * Una compra registrada por el almacén.
 *
 * @param  list<array{0: Articulo, 1: float, 2: float, 3?: bool}>  $lineas
 */
function comprar(array $lineas, string $fecha = '2026-11-02', array $extra = []): IngresoAlmacen
{
    actingAs(almacenero())
        ->post(route('almacen.ingresos.store'), datosDeCompra($lineas, $fecha, $extra))
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

/**
 * La misma factura escrita distinto —minúsculas, ceros de relleno— sigue
 * siendo la misma.
 */
it('rejects the same invoice from the same supplier twice', function (): void {
    $filtro = Articulo::factory()->create();
    $compra = comprar([[$filtro, 1, 10]], extra: ['serie_numero' => 'F001-123']);

    actingAs(almacenero())
        ->post(route('almacen.ingresos.store'), datosDeCompra([[$filtro, 1, 10]], '2026-11-03', [
            'proveedor_id' => $compra->proveedor_id,
            'serie_numero' => 'f001-00000123',
        ]))
        ->assertSessionHasErrors('serie_numero');

    // De otro proveedor, el mismo número no choca.
    actingAs(almacenero())
        ->post(route('almacen.ingresos.store'), datosDeCompra([[$filtro, 1, 10]], '2026-11-03', ['serie_numero' => 'F001-123']))
        ->assertSessionHasNoErrors();
});

it('asks for the exchange rate when the invoice is in dollars', function (): void {
    actingAs(almacenero())
        ->post(route('almacen.ingresos.store'), datosDeCompra([[Articulo::factory()->create(), 1, 10]], extra: [
            'moneda' => 'USD',
            'total_comprobante' => 11.80,
        ]))
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

    $compra = comprar([[$filtro, 1, 10]]);

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

it('lets the warehouse create a supplier or an item while registering a purchase', function (): void {
    actingAs(almacenero())
        ->get(route('almacen.ingresos.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('puedeCrearProveedor', true)
            ->where('puedeCrearArticulo', true));

    actingAs(actorConRol('gerencia'))
        ->get(route('almacen.ingresos.create'))
        ->assertForbidden();
});

// ─── Configuración ──────────────────────────────────────────────────────────

it('creates and renames categories without repeating them', function (): void {
    actingAs(almacenero())
        ->post(route('almacen.categorias.store'), ['nombre' => 'aire acondicionado'])
        ->assertSessionHasNoErrors();

    $categoria = CategoriaArticulo::query()->where('nombre', 'Aire Acondicionado')->sole();

    actingAs(almacenero())
        ->put(route('almacen.categorias.update', $categoria), ['nombre' => 'Filtros'])
        ->assertSessionHasErrors('nombre');

    actingAs(almacenero())
        ->put(route('almacen.categorias.update', $categoria), ['nombre' => 'Climatización'])
        ->assertSessionHasNoErrors();

    expect($categoria->refresh()->nombre)->toBe('Climatización');
});

/**
 * Quitar una categoría con artículos es la forma de fusionarla con otra.
 */
it('merges a category into another when it is removed', function (): void {
    $frenos = CategoriaArticulo::query()->where('nombre', 'Frenos')->sole();
    $repetida = CategoriaArticulo::factory()->create(['nombre' => 'Sistema De Frenos']);
    $pastilla = Articulo::factory()->create(['categoria_articulo_id' => $repetida->id]);

    actingAs(almacenero())
        ->delete(route('almacen.categorias.destroy', $repetida), ['mover_a' => $frenos->id])
        ->assertSessionHasNoErrors();

    expect(CategoriaArticulo::query()->find($repetida->id))->toBeNull()
        ->and($pastilla->refresh()->categoria_articulo_id)->toBe($frenos->id);
});

it('leaves the items without location when a shelf is removed without destination', function (): void {
    $estante = UbicacionAlmacen::factory()->create();
    $filtro = Articulo::factory()->create(['ubicacion_almacen_id' => $estante->id]);

    actingAs(almacenero())
        ->delete(route('almacen.ubicaciones.destroy', $estante))
        ->assertSessionHasNoErrors();

    expect($filtro->refresh()->ubicacion_almacen_id)->toBeNull();
});

it('creates shelves in capitals and assigns them to items', function (): void {
    actingAs(almacenero())
        ->post(route('almacen.ubicaciones.store'), ['nombre' => ' a-3 ', 'descripcion' => 'Filtros y fajas'])
        ->assertSessionHasNoErrors();

    $estante = UbicacionAlmacen::query()->sole();
    expect($estante->nombre)->toBe('A-3');

    actingAs(almacenero())
        ->post(route('almacen.articulos.store'), ['nombre' => 'Filtro de aire', 'unidad' => 'und', 'ubicacion_almacen_id' => $estante->id])
        ->assertSessionHasNoErrors();

    actingAs(almacenero())
        ->get(route('almacen.index', ['ubicacion' => $estante->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('articulos.data', 1)
            ->where('articulos.data.0.ubicacion', 'A-3'));
});

it('saves the minimums of many items at once', function (): void {
    $filtro = Articulo::factory()->create(['stock_minimo' => 5]);
    $faja = Articulo::factory()->create();

    actingAs(almacenero())
        ->put(route('almacen.minimos.update'), ['minimos' => [
            ['id' => $filtro->id, 'stock_minimo' => null],
            ['id' => $faja->id, 'stock_minimo' => 4],
        ]])
        ->assertSessionHasNoErrors();

    expect($filtro->refresh()->stock_minimo)->toBeNull()
        ->and((float) $faja->refresh()->stock_minimo)->toBe(4.0);
});

it('keeps the warehouse configuration to who manages the catalog', function (): void {
    actingAs(almacenero())->get(route('almacen.configuracion'))->assertOk();
    actingAs(actorConRol('taller'))->get(route('almacen.configuracion'))->assertForbidden();
    actingAs(actorConRol('taller'))->post(route('almacen.ubicaciones.store'), ['nombre' => 'X'])->assertForbidden();
});

// ─── Comprobantes ───────────────────────────────────────────────────────────

it('requires the file of the invoice', function (): void {
    $datos = datosDeCompra([[Articulo::factory()->create(), 1, 10]]);
    unset($datos['comprobante']);

    actingAs(almacenero())
        ->post(route('almacen.ingresos.store'), $datos)
        ->assertSessionHasErrors('comprobante');

    expect(IngresoAlmacen::query()->count())->toBe(0);
});

it('rejects a file that is neither a PDF nor a photo', function (): void {
    actingAs(almacenero())
        ->post(route('almacen.ingresos.store'), datosDeCompra([[Articulo::factory()->create(), 1, 10]], extra: [
            'comprobante' => UploadedFile::fake()->createWithContent('factura.txt', 'no es una factura'),
        ]))
        ->assertSessionHasErrors('comprobante');
});

/**
 * Un vale o un ticket no traen serie: no se exige. Una factura sí.
 */
it('asks for the series and number only on invoices and receipts', function (): void {
    actingAs(almacenero())
        ->post(route('almacen.ingresos.store'), datosDeCompra([[Articulo::factory()->create(), 1, 10]], extra: ['serie_numero' => null]))
        ->assertSessionHasErrors('serie_numero');

    actingAs(almacenero())
        ->post(route('almacen.ingresos.store'), datosDeCompra([[Articulo::factory()->create(), 1, 10]], extra: [
            'tipo_comprobante' => 'vale',
            'serie_numero' => null,
        ]))
        ->assertSessionHasNoErrors();
});

/**
 * El comprobante va al disco privado: no se abre por una dirección pública,
 * solo por la ruta del ingreso, que pide sesión y permiso.
 */
it('keeps the invoice file private', function (): void {
    $compra = comprar([[Articulo::factory()->create(), 1, 10]]);
    $archivo = $compra->getFirstMedia('comprobante');

    expect($archivo->disk)->toBe('local');
    Storage::disk('local')->assertExists("{$archivo->id}/{$archivo->file_name}");
    Storage::disk('public')->assertMissing("{$archivo->id}/{$archivo->file_name}");

    actingAs(almacenero())->get(route('almacen.ingresos.comprobante', $compra))->assertOk();
    actingAs(actorConRol('contador'))->get(route('almacen.ingresos.comprobante', $compra))->assertForbidden();
});

it('replaces the invoice file without touching the kardex', function (): void {
    $filtro = Articulo::factory()->create();
    $compra = comprar([[$filtro, 2, 10]]);
    $anterior = $compra->getFirstMedia('comprobante');

    actingAs(almacenero())
        ->post(route('almacen.ingresos.comprobante.update', $compra), ['comprobante' => pdfFalso('factura-legible.pdf')])
        ->assertSessionHasNoErrors();

    $nuevo = $compra->refresh()->getFirstMedia('comprobante');
    expect($nuevo->file_name)->toBe('factura-legible.pdf')
        ->and($nuevo->id)->not->toBe($anterior->id)
        ->and($compra->getMedia('comprobante'))->toHaveCount(1)
        ->and((float) $filtro->refresh()->stock)->toBe(2.0);

    actingAs(actorConRol('taller'))
        ->post(route('almacen.ingresos.comprobante.update', $compra), ['comprobante' => pdfFalso()])
        ->assertForbidden();
});

/**
 * El total que se digita tiene que cuadrar con los ítems: si no, algo se
 * escribió mal. Unos céntimos de redondeo sí se aceptan.
 */
it('checks the total of the invoice against its items', function (): void {
    $filtro = Articulo::factory()->create();

    actingAs(almacenero())
        ->post(route('almacen.ingresos.store'), datosDeCompra([[$filtro, 2, 100]], extra: ['total_comprobante' => 250]))
        ->assertSessionHasErrors('total_comprobante');

    // 2 × 100 + IGV = 236; el papel dice 236.05 por su redondeo.
    $compra = comprar([[$filtro, 2, 100]], extra: ['total_comprobante' => 236.05]);

    expect((float) $compra->total)->toBe(236.05)
        ->and((float) $compra->subtotal)->toBe(200.0)
        ->and((float) $compra->igv)->toBe(36.05)
        ->and((float) $compra->costo_total)->toBe(200.0);
});

/**
 * El descuento y el flete se reparten según lo que vale cada línea: las dos
 * valen 100, así que cada una carga la mitad de los −20 y los +40.
 */
it('spreads the discount and the freight over the cost of the items', function (): void {
    $filtro = Articulo::factory()->create();
    $faja = Articulo::factory()->create();

    $compra = comprar([[$filtro, 10, 10], [$faja, 1, 100]], extra: ['descuento' => 20, 'flete' => 40]);

    expect((float) $compra->subtotal)->toBe(220.0)
        ->and((float) $compra->total)->toBe(259.6)
        ->and((float) $compra->costo_total)->toBe(220.0)
        ->and((float) $filtro->refresh()->costo_promedio)->toBe(11.0)
        ->and((float) $faja->refresh()->costo_promedio)->toBe(110.0);
});

/**
 * Lo que el proveedor regala entra con el valor que se le pone, pero no se
 * paga: no suma al total del comprobante.
 */
it('values gifts from the supplier without adding them to the invoice', function (): void {
    $filtro = Articulo::factory()->create();
    $franela = Articulo::factory()->create();

    $compra = comprar([[$filtro, 1, 100], [$franela, 2, 5, true]]);

    expect((float) $compra->total)->toBe(118.0)
        ->and((float) $compra->costo_total)->toBe(110.0)
        ->and((float) $franela->refresh()->valor)->toBe(10.0)
        ->and($compra->detalles->firstWhere('articulo_id', $franela->id)->bonificacion)->toBeTrue();

    actingAs(almacenero())
        ->post(route('almacen.ingresos.store'), datosDeCompra([[$filtro, 1, 0]]))
        ->assertSessionHasErrors('detalles.0.precio_unitario');
});

/**
 * Comprado en dólares, el comprobante queda en dólares; el kardex, en soles.
 */
it('keeps a purchase in dollars in dollars', function (): void {
    $bateria = Articulo::factory()->create();

    $compra = comprar([[$bateria, 1, 100]], extra: ['moneda' => 'USD', 'tipo_cambio' => 3.8]);

    expect($compra->moneda)->toBe(Moneda::Dolares)
        ->and((float) $compra->total)->toBe(118.0)
        ->and((float) $compra->detalles->first()->precio_unitario)->toBe(100.0)
        ->and((float) $compra->costo_total)->toBe(380.0);

    actingAs(almacenero())
        ->get(route('almacen.proveedores.index'))
        ->assertInertia(fn (Assert $page) => $page->where('proveedores.data.0.comprado_usd', 118));
});

// ─── Cierre de mes ──────────────────────────────────────────────────────────

it('does not register anything in a closed month', function (): void {
    $filtro = Articulo::factory()->create();

    actingAs(almacenero())
        ->post(route('almacen.ingresos.store'), datosDeCompra([[$filtro, 1, 10]], '2026-10-31'))
        ->assertSessionHasErrors('fecha');

    actingAs(almacenero())
        ->post(route('almacen.inventarios.store'), ['fecha' => '2026-10-31'])
        ->assertSessionHasErrors('fecha');

    // El comprobante sí puede ser del mes pasado: lo cerrado es el kardex.
    comprar([[$filtro, 1, 10]], '2026-11-02', ['fecha_emision' => '2026-10-30']);
});

it('does not void documents of a closed month', function (): void {
    $this->travelTo(Carbon::parse('2026-10-20 17:00:00'));
    $filtro = Articulo::factory()->create();
    $compra = comprar([[$filtro, 5, 10]], '2026-10-20');
    $vale = despachar([[$filtro, 1]], '2026-10-20');
    $conteo = InventarioAlmacen::factory()->create(['fecha' => '2026-10-20']);
    $conteo->detalles()->create(['articulo_id' => $filtro->id, 'cantidad_contada' => 4]);

    $this->travelTo(Carbon::parse('2026-11-09 17:00:00'));

    actingAs(almacenero())->post(route('almacen.ingresos.anular', $compra), ['motivo' => 'Factura equivocada'])->assertSessionHasErrors('motivo');
    actingAs(almacenero())->post(route('almacen.salidas.anular', $vale), ['motivo' => 'Se registró dos veces'])->assertSessionHasErrors('motivo');
    actingAs(almacenero())->post(route('almacen.inventarios.cerrar', $conteo))->assertSessionHasErrors('inventario');

    expect($compra->refresh()->estaAnulado())->toBeFalse()
        ->and($vale->refresh()->estaAnulado())->toBeFalse()
        ->and($conteo->refresh()->estado)->toBe(EstadoInventario::Abierto);

    // Un conteo abierto no movió nada: se puede descartar igual.
    actingAs(almacenero())->post(route('almacen.inventarios.anular', $conteo), ['motivo' => 'Quedó del mes pasado'])->assertSessionHasNoErrors();
});

// ─── Saldo inicial y bajas ──────────────────────────────────────────────────

it('asks for the cost of the opening balance', function (): void {
    $faja = Articulo::factory()->create();
    $conteo = InventarioAlmacen::factory()->create();

    actingAs(almacenero())
        ->post(route('almacen.inventarios.lineas.store', $conteo), ['articulo_id' => $faja->id, 'cantidad_contada' => 5])
        ->assertSessionHasErrors('costo_unitario');

    // Con historial ya no es saldo inicial: el costo es opcional.
    comprar([[$faja, 1, 10]]);

    actingAs(almacenero())
        ->post(route('almacen.inventarios.lineas.store', $conteo), ['articulo_id' => $faja->id, 'cantidad_contada' => 5])
        ->assertSessionHasNoErrors();
});

/**
 * Un artículo con stock solo lo da de baja el admin. Dado de baja sigue
 * sumando al inventario y se puede despachar hasta vaciarlo.
 */
it('lets only the admin retire an item that still has stock', function (): void {
    $filtro = Articulo::factory()->create();
    comprar([[$filtro, 3, 10]]);
    $datos = ['nombre' => $filtro->nombre, 'unidad' => 'und', 'activo' => false];

    actingAs(almacenero())->put(route('almacen.articulos.update', $filtro), $datos)->assertSessionHasErrors('activo');
    expect($filtro->refresh()->activo)->toBeTrue();

    actingAs(actorConRol('admin'))->put(route('almacen.articulos.update', $filtro), $datos)->assertSessionHasNoErrors();
    expect($filtro->refresh()->activo)->toBeFalse();

    actingAs(almacenero())
        ->get(route('almacen.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('resumen.valor', 30)
            ->where('resumen.baja_con_stock', 1));

    actingAs(almacenero())
        ->get(route('almacen.salidas.create'))
        ->assertInertia(fn (Assert $page) => $page->where('articulos.0.id', $filtro->id));

    despachar([[$filtro, 3]]);
    expect((float) $filtro->refresh()->stock)->toBe(0.0);

    // Sin stock, cualquiera con catálogo puede dar de baja.
    $otro = Articulo::factory()->create();
    actingAs(almacenero())->put(route('almacen.articulos.update', $otro), ['nombre' => $otro->nombre, 'unidad' => 'und', 'activo' => false])->assertSessionHasNoErrors();
});

it('nets the returns out of the consumption per unit', function (): void {
    $filtro = Articulo::factory()->create();
    $tracto = Vehiculo::factory()->create();
    comprar([[$filtro, 10, 10]]);
    $vale = despachar([[$filtro, 4]], '2026-11-05', $tracto);

    actingAs(almacenero())->post(route('almacen.salidas.devolver', $vale), [
        'fecha' => '2026-11-06',
        'detalles' => [['detalle_salida_id' => $vale->detalles->first()->id, 'cantidad' => 1]],
    ])->assertSessionHasNoErrors();

    actingAs(almacenero())
        ->get(route('almacen.articulos.show', $filtro))
        ->assertInertia(fn (Assert $page) => $page
            ->where('consumo.0.cantidad', 3)
            ->where('consumo.0.costo', 30));
});
