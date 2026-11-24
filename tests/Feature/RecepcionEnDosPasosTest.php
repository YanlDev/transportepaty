<?php

use App\Enums\TipoComprobante;
use App\Models\Articulo;
use App\Models\IngresoAlmacen;
use App\Models\PagoCompra;
use App\Models\Proveedor;
use App\Models\SalidaAlmacen;
use App\Models\User;
use App\Models\ValeCompra;
use App\Models\Vehiculo;
use App\Services\FirmaDeVales;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

/*
 * La compra se recibe en dos pasos: llega la mercadería y entra al stock con
 * los precios del pedido; después llega el comprobante y el costo se corrige.
 * Y una emergencia en ruta ya instalada entra y sale sola al aprobarse.
 */
beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-11-19 17:00:00'));
    Storage::fake('public');
    Storage::fake('local');

    config([
        'transpaty.compras.clave_firma' => app(FirmaDeVales::class)->generarClaves()['privada'],
        'transpaty.compras.vale_obligatorio_desde' => '2026-11-01',
    ]);

    $this->almacen = actorConRol('almacen');
    $this->gerencia = actorConRol('gerencia');
    $this->filtro = Articulo::factory()->create(['nombre' => 'FILTRO DE ACEITE']);
});

/** Un pedido aprobado de 4 filtros a S/ 118 (S/ 472). */
function pedidoAprobadoDeFiltros(User $pide, User $aprueba, Articulo $filtro, array $extra = []): ValeCompra
{
    actingAs($pide)
        ->post(route('almacen.compras.store'), [
            'tipo' => 'normal',
            'proveedor_id' => Proveedor::factory()->create()->id,
            'moneda' => 'PEN',
            'motivo' => 'Reposición de filtros',
            'sustento_precio' => 'Precio de lista del proveedor.',
            'detalles' => [['es_servicio' => false, 'articulo_id' => $filtro->id, 'cantidad' => 4, 'precio_unitario' => 118]],
            ...$extra,
        ])
        ->assertSessionHasNoErrors();

    $pedido = ValeCompra::query()->latest('id')->firstOrFail();
    actingAs($aprueba)->post(route('almacen.compras.aprobar', $pedido))->assertSessionHasNoErrors();

    return $pedido->refresh();
}

function facturaDePrueba(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('factura.pdf', "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n");
}

it('receives the goods first, with the prices of the order', function (): void {
    $pedido = pedidoAprobadoDeFiltros($this->almacen, $this->gerencia, $this->filtro);

    actingAs($this->almacen)
        ->get(route('almacen.compras.show', $pedido))
        ->assertInertia(fn (Assert $page) => $page
            ->where('vale.por_recibir.0.articulo_id', $this->filtro->id)
            ->where('vale.por_recibir.0.pendiente', 4));

    // Es de una sola compra: no se recibe a medias.
    actingAs($this->almacen)
        ->post(route('almacen.compras.recibir', $pedido), ['fecha' => '2026-11-19', 'detalles' => [['articulo_id' => $this->filtro->id, 'cantidad' => 3]]])
        ->assertSessionHasErrors('recepcion');

    actingAs($this->almacen)
        ->post(route('almacen.compras.recibir', $pedido), ['fecha' => '2026-11-19', 'detalles' => [['articulo_id' => $this->filtro->id, 'cantidad' => 4]]])
        ->assertSessionHasNoErrors();

    $ingreso = IngresoAlmacen::query()->sole();

    expect($ingreso->comprobante_pendiente)->toBeTrue()
        ->and($ingreso->tipo_comprobante)->toBeNull()
        ->and((float) $ingreso->total)->toBe(472.0)
        // Sin comprobante, lo pagado es el costo.
        ->and((float) $ingreso->costo_total)->toBe(472.0)
        ->and((float) $this->filtro->refresh()->stock)->toBe(4.0)
        ->and((float) $this->filtro->costo_promedio)->toBe(118.0);

    // Ya entró todo: no se recibe dos veces.
    actingAs($this->almacen)
        ->post(route('almacen.compras.recibir', $pedido), ['fecha' => '2026-11-19', 'detalles' => [['articulo_id' => $this->filtro->id, 'cantidad' => 4]]])
        ->assertSessionHasErrors('recepcion');
});

it('fixes the cost when the invoice arrives, even for what already left', function (): void {
    $pedido = pedidoAprobadoDeFiltros($this->almacen, $this->gerencia, $this->filtro);
    actingAs($this->almacen)
        ->post(route('almacen.compras.recibir', $pedido), ['fecha' => '2026-11-19', 'detalles' => [['articulo_id' => $this->filtro->id, 'cantidad' => 4]]])
        ->assertSessionHasNoErrors();
    $ingreso = IngresoAlmacen::query()->sole();

    // Antes del comprobante ya se entregó uno a una unidad.
    actingAs($this->almacen)
        ->post(route('almacen.salidas.store'), [
            'fecha' => '2026-11-19', 'destino' => 'unidad', 'vehiculo_id' => Vehiculo::factory()->create()->id,
            'entregado_a' => 'Mecánico de turno', 'detalles' => [['articulo_id' => $this->filtro->id, 'cantidad' => 1]],
        ])
        ->assertSessionHasNoErrors();

    $comprobante = fn (array $extra = []): array => [
        'fecha_emision' => '2026-11-19', 'tipo_comprobante' => 'factura', 'serie_numero' => 'F001-00077',
        'precios_con_igv' => true, 'total_comprobante' => 472, 'comprobante' => facturaDePrueba(), ...$extra,
    ];

    // El total tiene que ser lo que el pedido autorizó.
    actingAs($this->almacen)
        ->post(route('almacen.ingresos.completar', $ingreso), $comprobante(['total_comprobante' => 500]))
        ->assertSessionHasErrors();

    actingAs($this->almacen)
        ->post(route('almacen.ingresos.completar', $ingreso), $comprobante())
        ->assertSessionHasNoErrors();

    $ingreso->refresh();

    expect($ingreso->comprobante_pendiente)->toBeFalse()
        ->and($ingreso->tipo_comprobante)->toBe(TipoComprobante::Factura)
        ->and($ingreso->serie_numero)->toBe('F001-00077')
        // Con factura el costo es sin IGV: 472 / 1.18 = 400.
        ->and((float) $ingreso->costo_total)->toBe(400.0)
        ->and((float) $this->filtro->refresh()->costo_promedio)->toBe(100.0)
        ->and((float) $this->filtro->stock)->toBe(3.0)
        // Lo que ya había salido también quedó a su costo real.
        ->and((float) SalidaAlmacen::query()->sole()->costo_total)->toBe(100.0)
        ->and($ingreso->getMedia('comprobante'))->toHaveCount(1);

    // Ya completo, no se vuelve a completar.
    actingAs($this->almacen)->post(route('almacen.ingresos.completar', $ingreso), $comprobante(['serie_numero' => 'F001-00078']))->assertNotFound();
});

it('lists the receptions that still wait for their invoice', function (): void {
    $pedido = pedidoAprobadoDeFiltros($this->almacen, $this->gerencia, $this->filtro);
    actingAs($this->almacen)
        ->post(route('almacen.compras.recibir', $pedido), ['fecha' => '2026-11-19', 'detalles' => [['articulo_id' => $this->filtro->id, 'cantidad' => 4]]])
        ->assertSessionHasNoErrors();
    $ingreso = IngresoAlmacen::query()->sole();

    actingAs($this->almacen)
        ->get(route('almacen.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('porHacer.sin_comprobante', 1)
            ->where('porHacer.sin_comprobante.0.id', $ingreso->id)
            ->has('porHacer.por_recibir', 0));

    actingAs($this->almacen)
        ->get(route('almacen.ingresos.show', $ingreso))
        ->assertInertia(fn (Assert $page) => $page
            ->where('ingreso.comprobante_pendiente', true)
            ->where('puedeCompletar', true)
            ->where('ingreso.detalles.0.precio_unitario', 118));
});

it('takes an emergency already fitted on the road straight to the unit', function (): void {
    $operaciones = actorConRol('operaciones');
    $tracto = Vehiculo::factory()->create();

    actingAs($this->almacen)
        ->post(route('almacen.compras.store'), [
            'tipo' => 'emergencia',
            'proveedor_id' => Proveedor::factory()->create()->id,
            'moneda' => 'PEN',
            'motivo' => 'Se reventó el filtro en ruta, en Nazca',
            'vehiculo_id' => $tracto->id,
            'directo_a_unidad' => true,
            'detalles' => [['es_servicio' => false, 'articulo_id' => $this->filtro->id, 'cantidad' => 1, 'precio_unitario' => 150]],
        ])
        ->assertSessionHasNoErrors();
    $pedido = ValeCompra::query()->sole();

    expect($pedido->directo_a_unidad)->toBeTrue();

    // La aprueba operaciones (es emergencia) y todo pasa solo.
    actingAs($operaciones)->post(route('almacen.compras.aprobar', $pedido))->assertSessionHasNoErrors();

    $ingreso = IngresoAlmacen::query()->sole();
    $salida = SalidaAlmacen::query()->sole();

    expect($ingreso->comprobante_pendiente)->toBeTrue()
        ->and($salida->vehiculo_id)->toBe($tracto->id)
        ->and((float) $salida->costo_total)->toBe(150.0)
        // Entró y salió: el almacén queda como estaba.
        ->and((float) $this->filtro->refresh()->stock)->toBe(0.0);
});

it('opens the warehouse home with an approved order that was already received', function (): void {
    // Con más de un pedido en la lista es cuando la carga perezosa salta.
    pedidoAprobadoDeFiltros($this->almacen, $this->gerencia, Articulo::factory()->create());
    $pedido = pedidoAprobadoDeFiltros($this->almacen, $this->gerencia, $this->filtro);
    actingAs($this->almacen)
        ->post(route('almacen.compras.recibir', $pedido), ['fecha' => '2026-11-19', 'detalles' => [['articulo_id' => $this->filtro->id, 'cantidad' => 4]]])
        ->assertSessionHasNoErrors();

    // El pedido ya se usó: la portada lo saca de «por recibir» sin tropezar
    // al calcular en qué va (con la carga perezosa prohibida, como en local).
    foreach ([$this->almacen, $this->gerencia] as $quien) {
        actingAs($quien)
            ->get(route('almacen.index'))
            ->assertSuccessful()
            ->assertInertia(fn (Assert $page) => $page->where('porHacer.por_aprobar', []));
    }

    actingAs($this->almacen)
        ->get(route('almacen.index'))
        ->assertInertia(fn (Assert $page) => $page->has('porHacer.por_recibir', 1)->has('porHacer.sin_comprobante', 1));
});

it('receives an order in dollars in two steps, asking only for the exchange rate', function (): void {
    $pedido = pedidoAprobadoDeFiltros($this->almacen, $this->gerencia, $this->filtro, ['moneda' => 'USD']);
    $recibir = fn (array $extra = []): array => ['fecha' => '2026-11-19', 'detalles' => [['articulo_id' => $this->filtro->id, 'cantidad' => 4]], ...$extra];

    actingAs($this->almacen)
        ->get(route('almacen.compras.show', $pedido))
        ->assertInertia(fn (Assert $page) => $page->where('vale.por_recibir.0.pendiente', 4));

    // Sin tipo de cambio no entra: el kardex va en soles.
    actingAs($this->almacen)->post(route('almacen.compras.recibir', $pedido), $recibir())->assertSessionHasErrors('tipo_cambio');

    actingAs($this->almacen)->post(route('almacen.compras.recibir', $pedido), $recibir(['tipo_cambio' => 3.75]))->assertSessionHasNoErrors();

    $ingreso = IngresoAlmacen::query()->sole();

    // US$ 472 a 3.75 = S/ 1,770 mientras no llega la factura.
    expect($ingreso->comprobante_pendiente)->toBeTrue()
        ->and((float) $ingreso->tipo_cambio)->toBe(3.75)
        ->and((float) $ingreso->costo_total)->toBe(1770.0)
        ->and((float) $this->filtro->refresh()->stock)->toBe(4.0);

    // La factura trae otro tipo de cambio: el costo queda sin IGV y a ese cambio (400 × 3.80).
    actingAs($this->almacen)
        ->post(route('almacen.ingresos.completar', $ingreso), [
            'tipo_comprobante' => 'factura', 'serie_numero' => 'F001-00090', 'fecha_emision' => '2026-11-19',
            'precios_con_igv' => true, 'total_comprobante' => 472, 'tipo_cambio' => 3.8, 'comprobante' => facturaDePrueba(),
        ])
        ->assertSessionHasNoErrors();

    expect((float) $ingreso->refresh()->tipo_cambio)->toBe(3.8)
        ->and((float) $ingreso->costo_total)->toBe(1520.0)
        ->and((float) $this->filtro->refresh()->costo_promedio)->toBe(380.0);
});

it('receives an approved order weeks later, paid or not: orders do not expire', function (): void {
    $pagado = pedidoAprobadoDeFiltros($this->almacen, $this->gerencia, $this->filtro);
    $sinPagar = pedidoAprobadoDeFiltros($this->almacen, $this->gerencia, $this->filtro);
    PagoCompra::factory()->create(['vale_compra_id' => $pagado->id, 'monto' => 472, 'fecha' => '2026-11-19']);

    $this->travelTo(Carbon::parse('2026-11-30 10:00:00'));
    $recibir = ['fecha' => '2026-11-30', 'detalles' => [['articulo_id' => $this->filtro->id, 'cantidad' => 4]]];

    expect($pagado->refresh()->situacion()->sirveParaComprar())->toBeTrue()
        ->and($sinPagar->refresh()->situacion()->sirveParaComprar())->toBeTrue();

    actingAs($this->almacen)->post(route('almacen.compras.recibir', $pagado), $recibir)->assertSessionHasNoErrors();
    actingAs($this->almacen)->post(route('almacen.compras.recibir', $sinPagar), $recibir)->assertSessionHasNoErrors();

    expect((float) $this->filtro->refresh()->stock)->toBe(8.0);
});
