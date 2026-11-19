<?php

use App\Enums\EstadoOrdenTrabajo;
use App\Enums\TipoVehiculo;
use App\Models\Articulo;
use App\Models\OrdenTrabajo;
use App\Models\Proveedor;
use App\Models\RepuestoOrden;
use App\Models\SalidaAlmacen;
use App\Models\ValeCompra;
use App\Models\Vehiculo;
use App\Services\FirmaDeVales;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

/*
 * El circuito completo entre la orden de trabajo y el almacén, de punta a
 * punta y con cada rol en su paso: pre-orden → requerimiento → aprobación →
 * compra de lo que falta (vale, aprobación de gerencia, ingreso) → vale de
 * salida a la orden → el repuesto figura entregado → trabajo → validación.
 */
beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-11-19 17:00:00'));
    Storage::fake('public');
    Storage::fake('local');

    config([
        'transpaty.compras.clave_firma' => app(FirmaDeVales::class)->generarClaves()['privada'],
        'transpaty.compras.vale_obligatorio_desde' => '2026-11-01',
    ]);
});

it('runs a work order from the pre-order to the validated job, buying and issuing its part', function (): void {
    $operaciones = actorConRol('operaciones');
    $mecanico = actorConRol('mecanico');
    $almacen = actorConRol('almacen');
    $gerencia = actorConRol('gerencia');
    $tracto = Vehiculo::factory()->create(['tipo' => TipoVehiculo::Tracto]);
    $valvula = Articulo::factory()->create(['nombre' => 'VÁLVULA RELÉ']);
    $proveedor = Proveedor::factory()->create();
    $foto = fn (): UploadedFile => UploadedFile::fake()->image('foto.jpg', 800, 600);
    $pdf = UploadedFile::fake()->createWithContent('factura.pdf', "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n");

    // 1. Operaciones crea la pre-orden.
    actingAs($operaciones)
        ->post(route('taller.solicitudes.store'), ['vehiculo_id' => $tracto->id, 'trabajo' => 'Fuga de aire en el eje 3', 'prioridad' => 'urgente', 'mecanicos' => [$mecanico->id]])
        ->assertSessionHasNoErrors();
    $orden = OrdenTrabajo::query()->sole();

    // 2. El mecánico arma y envía el requerimiento.
    actingAs($mecanico)->post(route('mis-ordenes.tareas.store', $orden), ['descripcion' => 'Cambiar la válvula relé'])->assertSessionHasNoErrors();
    actingAs($mecanico)->post(route('mis-ordenes.repuestos.store', $orden), ['articulo_id' => $valvula->id, 'cantidad' => 1])->assertSessionHasNoErrors();
    actingAs($mecanico)->post(route('mis-ordenes.fotos.store', $orden), ['coleccion' => 'antes', 'fotos' => [$foto()]])->assertSessionHasNoErrors();
    actingAs($mecanico)->post(route('mis-ordenes.enviar', $orden))->assertSessionHasNoErrors();

    // Antes de aprobarse, el almacén no le puede sacar nada.
    $vale = fn (): array => [
        'fecha' => '2026-11-19',
        'destino' => 'orden_trabajo',
        'orden_trabajo_id' => $orden->id,
        'vehiculo_id' => $tracto->id,
        'entregado_a' => $mecanico->name,
        'detalles' => [['articulo_id' => $valvula->id, 'cantidad' => 1]],
    ];
    actingAs($almacen)->post(route('almacen.salidas.store'), $vale())->assertSessionHasErrors('orden_trabajo_id');

    // 3. Operaciones aprueba: nace la orden.
    actingAs($operaciones)->post(route('mantenimiento.ordenes.requerimiento.aprobar', $orden))->assertSessionHasNoErrors();
    expect($orden->refresh()->estado)->toBe(EstadoOrdenTrabajo::Pendiente);

    // Aprobada pero sin stock: el vale de salida se rechaza.
    actingAs($almacen)->post(route('almacen.salidas.store'), $vale())->assertSessionHasErrors();

    // 4. Se pide la compra, gerencia la aprueba y el almacén la recibe.
    actingAs($almacen)
        ->post(route('almacen.compras.store'), [
            'tipo' => 'normal',
            'proveedor_id' => $proveedor->id,
            'moneda' => 'PEN',
            'motivo' => "Válvula para la {$orden->numero()}",
            'sustento_precio' => 'Precio de lista del proveedor.',
            'orden_trabajo_id' => $orden->id,
            'detalles' => [['es_servicio' => false, 'articulo_id' => $valvula->id, 'cantidad' => 1, 'precio_unitario' => 180]],
        ])
        ->assertSessionHasNoErrors();
    $compra = ValeCompra::query()->sole();

    actingAs($gerencia)->post(route('almacen.compras.aprobar', $compra))->assertSessionHasNoErrors();

    actingAs($almacen)
        ->post(route('almacen.ingresos.store'), [
            'fecha' => '2026-11-19',
            'fecha_emision' => '2026-11-19',
            'proveedor_id' => $proveedor->id,
            'vale_compra_id' => $compra->id,
            'tipo_comprobante' => 'factura',
            'serie_numero' => 'F001-00123',
            'moneda' => 'PEN',
            'precios_con_igv' => true,
            'total_comprobante' => 180,
            'comprobante' => $pdf,
            'detalles' => [['articulo_id' => $valvula->id, 'cantidad' => 1, 'precio_unitario' => 180]],
        ])
        ->assertSessionHasNoErrors();
    expect((float) $valvula->refresh()->stock)->toBe(1.0);

    // 5. El almacén entrega el repuesto con vale a nombre de la orden.
    actingAs($almacen)->post(route('almacen.salidas.store'), $vale())->assertSessionHasNoErrors();
    expect(SalidaAlmacen::query()->sole()->orden_trabajo_id)->toBe($orden->id)
        ->and((float) $valvula->refresh()->stock)->toBe(0.0);

    // El mecánico lo ve entregado, sin costos.
    actingAs($mecanico)
        ->get(route('mis-ordenes.show', $orden))
        ->assertInertia(fn (Assert $page) => $page
            ->where('orden.repuestos.0.aprobado', true)
            ->where('orden.repuestos.0.entregado', 1)
            ->where('orden.materiales.0.nombre', 'VÁLVULA RELÉ')
            ->missing('orden.materiales.0.costo'));

    // 6. El mecánico trabaja y termina; operaciones valida.
    actingAs($mecanico)->post(route('mis-ordenes.iniciar', $orden))->assertSessionHasNoErrors();
    actingAs($mecanico)->post(route('mis-ordenes.tareas.marcar', [$orden, $orden->tareas()->sole()]), ['hecha' => true])->assertSessionHasNoErrors();
    actingAs($mecanico)->post(route('mis-ordenes.terminar', $orden), ['fotos' => [$foto()]])->assertSessionHasNoErrors();

    actingAs($operaciones)
        ->put(route('mantenimiento.ordenes.update', $orden), [
            'tipo' => 'correctiva', 'estado' => 'finalizada', 'fecha' => '2026-11-19', 'trabajo' => $orden->trabajo,
        ])
        ->assertSessionHasNoErrors();

    expect($orden->refresh()->estado)->toBe(EstadoOrdenTrabajo::Finalizada);

    // El costo de la orden es el del repuesto que salió (sin IGV: factura).
    actingAs($gerencia)
        ->get(route('mantenimiento.show', $tracto))
        ->assertInertia(fn (Assert $page) => $page->where('ordenes.0.costo', fn ($costo) => abs((float) $costo - 152.54) < 0.01));
});

/** Una orden que pasó por requerimiento, ya aprobada, con ese repuesto pedido. */
function ordenConPedido(Articulo|string $repuesto, float $cantidad): OrdenTrabajo
{
    $mecanico = actorConRol('mecanico');
    $orden = OrdenTrabajo::factory()->create([
        'estado' => EstadoOrdenTrabajo::Pendiente,
        'aprobado_at' => now(),
        'tecnico' => null,
    ]);
    $orden->mecanicos()->attach($mecanico->id, ['responsable' => true]);

    RepuestoOrden::factory()->create([
        'orden_trabajo_id' => $orden->id,
        'articulo_id' => $repuesto instanceof Articulo ? $repuesto->id : null,
        'descripcion' => $repuesto instanceof Articulo ? $repuesto->nombre : $repuesto,
        'cantidad' => $cantidad,
        'aprobado_at' => now(),
    ]);

    return $orden;
}

/** Deja ese stock en el artículo con una compra sin pedido (antes de que fuera obligatorio). */
function conStock(Articulo $articulo, float $cantidad): Articulo
{
    config(['transpaty.compras.vale_obligatorio_desde' => null]);

    actingAs(actorConRol('almacen'))
        ->post(route('almacen.ingresos.store'), [
            'fecha' => '2026-11-19',
            'fecha_emision' => '2026-11-19',
            'proveedor_id' => Proveedor::factory()->create()->id,
            'tipo_comprobante' => 'factura',
            'serie_numero' => 'F001-'.fake()->unique()->numerify('#####'),
            'moneda' => 'PEN',
            'precios_con_igv' => true,
            'total_comprobante' => $cantidad * 100,
            'comprobante' => UploadedFile::fake()->createWithContent('factura.pdf', "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n"),
            'detalles' => [['articulo_id' => $articulo->id, 'cantidad' => $cantidad, 'precio_unitario' => 100]],
        ])
        ->assertSessionHasNoErrors();

    return $articulo->refresh();
}

/** Un vale de salida de ese artículo a esa orden (o a consumo). */
function entregar(Articulo $articulo, float $cantidad, ?OrdenTrabajo $orden = null): array
{
    return [
        'fecha' => '2026-11-19',
        'destino' => $orden === null ? 'consumo' : 'orden_trabajo',
        'orden_trabajo_id' => $orden?->id,
        'entregado_a' => 'Mecánico de turno',
        'detalles' => [['articulo_id' => $articulo->id, 'cantidad' => $cantidad]],
    ];
}

it('starts the delivery from what the order has approved, for its mechanic', function (): void {
    $valvula = conStock(Articulo::factory()->create(), 1);
    $orden = ordenConPedido($valvula, 2);

    actingAs(actorConRol('almacen'))
        ->get(route('almacen.salidas.create', ['orden' => $orden->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('inicial.orden_trabajo_id', $orden->id)
            ->where('inicial.entregado_a', $orden->mecanicos->first()->name)
            // Pidió 2 y hay 1: se propone lo que alcanza.
            ->where('inicial.detalles', [['articulo_id' => $valvula->id, 'cantidad' => 1]])
            ->where('pedido.lineas.0.pendiente', 2));
});

it('does not hand an order what it did not ask for, nor more than it asked', function (): void {
    $valvula = conStock(Articulo::factory()->create(), 5);
    $otro = conStock(Articulo::factory()->create(), 5);
    $orden = ordenConPedido($valvula, 2);
    $almacen = actorConRol('almacen');

    actingAs($almacen)->post(route('almacen.salidas.store'), entregar($otro, 1, $orden))->assertSessionHasErrors('detalles.0.cantidad');
    actingAs($almacen)->post(route('almacen.salidas.store'), entregar($valvula, 3, $orden))->assertSessionHasErrors('detalles.0.cantidad');
    actingAs($almacen)->post(route('almacen.salidas.store'), entregar($valvula, 2, $orden))->assertSessionHasNoErrors();

    // Ya recibió todo: no le sale más.
    actingAs($almacen)->post(route('almacen.salidas.store'), entregar($valvula, 1, $orden))->assertSessionHasErrors('detalles.0.cantidad');

    // Si de verdad hace falta, quien gestiona la orden lo agrega (queda aprobado) y entonces sale.
    actingAs(actorConRol('operaciones'))
        ->post(route('mantenimiento.ordenes.repuestos.store', $orden), ['articulo_id' => $otro->id, 'cantidad' => 1])
        ->assertSessionHasNoErrors();
    actingAs($almacen)->post(route('almacen.salidas.store'), entregar($otro, 1, $orden))->assertSessionHasNoErrors();
});

it('keeps the stock approved for an open order away from everything else', function (): void {
    $valvula = conStock(Articulo::factory()->create(), 3);
    $orden = ordenConPedido($valvula, 2);
    $almacen = actorConRol('almacen');

    // Hay 3 y 2 están apartados: a consumo solo sale 1.
    actingAs($almacen)->post(route('almacen.salidas.store'), entregar($valvula, 2))->assertSessionHasErrors('detalles.0.cantidad');
    expect(session('errors')->first('detalles.0.cantidad'))->toContain($orden->numero());
    actingAs($almacen)->post(route('almacen.salidas.store'), entregar($valvula, 1))->assertSessionHasNoErrors();

    // El mecánico de otra orden ya no lo ve disponible.
    $otra = ordenConPedido('Otro repuesto', 1);
    actingAs($otra->mecanicos->first())
        ->get(route('mis-ordenes.show', $otra))
        ->assertInertia(fn (Assert $page) => $page->where('articulos', fn ($articulos) => collect($articulos)->firstWhere('id', $valvula->id)['hay'] === false));

    // Su propia reserva no le estorba a la orden.
    actingAs($almacen)->post(route('almacen.salidas.store'), entregar($valvula, 2, $orden))->assertSessionHasNoErrors();
});

it('opens the warehouse with what each one has to do', function (): void {
    $valvula = conStock(Articulo::factory()->create(['nombre' => 'VÁLVULA RELÉ']), 1);
    $filtro = Articulo::factory()->create(['nombre' => 'FILTRO DE AIRE']);
    $conStock = ordenConPedido($valvula, 1);
    $sinStock = ordenConPedido($filtro, 2);
    $enTexto = ordenConPedido('Manguera de aire de 1/2', 1);

    actingAs(actorConRol('almacen'))
        ->get(route('almacen.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('porHacer.entregas', 2)
            ->where('porHacer.entregas.0.id', $conStock->id)
            ->where('porHacer.entregas.0.se_puede', true)
            ->where('porHacer.entregas.1.se_puede', false)
            ->has('porHacer.por_comprar', 2)
            ->where('porHacer.por_comprar.0.id', $sinStock->id)
            ->where('porHacer.por_comprar.0.lineas.0.falta', 2)
            ->where('porHacer.por_comprar.1.lineas.0.en_catalogo', false));

    // El pedido de compra de la orden nace con lo que le falta.
    actingAs(actorConRol('almacen'))
        ->get(route('almacen.compras.create', ['orden' => $sinStock->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('inicial.detalles.0.articulo_id', $filtro->id)
            ->where('inicial.detalles.0.cantidad', 2));

    // Lo pedido en texto pasa al catálogo con un toque.
    $linea = $enTexto->repuestos()->sole();
    actingAs(actorConRol('almacen'))
        ->post(route('mantenimiento.ordenes.repuestos.catalogo', [$enTexto, $linea]))
        ->assertSessionHasNoErrors();

    expect($linea->refresh()->articulo?->nombre)->toBe('MANGUERA DE AIRE DE 1/2');

    // Gerencia ve los pedidos por aprobar; el mecánico no entra.
    actingAs(actorConRol('gerencia'))
        ->get(route('almacen.index'))
        ->assertInertia(fn (Assert $page) => $page->where('porHacer.entregas', null)->has('porHacer.por_aprobar', 0));
    actingAs(actorConRol('mecanico'))->get(route('almacen.index'))->assertForbidden();
});
