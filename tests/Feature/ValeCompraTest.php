<?php

use App\Enums\EstadoValeCompra;
use App\Enums\SituacionValeCompra;
use App\Models\Articulo;
use App\Models\ConsultaValeCompra;
use App\Models\IngresoAlmacen;
use App\Models\OrdenTrabajo;
use App\Models\Proveedor;
use App\Models\User;
use App\Models\ValeCompra;
use App\Models\Vehiculo;
use App\Services\FirmaDeVales;
use App\Services\MaterialesDeOrden;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

/*
 * El circuito de compra: pedido → aprobación → vale firmado → compra →
 * ingreso al almacén ligado al vale. El reloj queda el 9 de noviembre de
 * 2026 a mediodía de Lima; el vale es obligatorio desde el 1.
 */
beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-11-09 17:00:00'));
    Storage::fake('public');
    Storage::fake('local');

    config([
        'transpaty.compras.clave_firma' => app(FirmaDeVales::class)->generarClaves()['privada'],
        'transpaty.compras.vale_obligatorio_desde' => '2026-11-01',
    ]);
});

function documentoDePrueba(string $nombre = 'documento.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($nombre, "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n");
}

/**
 * Los datos de un pedido de compra normal, con su sustento de precio.
 *
 * @param  list<array{0: Articulo|string, 1: float, 2: float}>  $lineas  artículo (o descripción de un servicio), cantidad y precio
 * @return array<string, mixed>
 */
function pedidoDeCompra(array $lineas, array $extra = []): array
{
    return [
        'tipo' => 'normal',
        'proveedor_id' => Proveedor::factory()->create()->id,
        'moneda' => 'PEN',
        'motivo' => 'Reposición de filtros para los preventivos',
        'sustento_precio' => 'Mismo precio que la última compra a este proveedor.',
        'detalles' => array_map(fn (array $linea): array => $linea[0] instanceof Articulo
            ? ['es_servicio' => false, 'articulo_id' => $linea[0]->id, 'cantidad' => $linea[1], 'precio_unitario' => $linea[2]]
            : ['es_servicio' => true, 'descripcion' => $linea[0], 'cantidad' => $linea[1], 'precio_unitario' => $linea[2]], $lineas),
        ...$extra,
    ];
}

/**
 * Un vale pedido por taller y aprobado por gerencia.
 *
 * @param  list<array{0: Articulo|string, 1: float, 2: float}>  $lineas
 */
function valeAprobado(array $lineas, array $extra = []): ValeCompra
{
    actingAs(actorConRol('taller'))
        ->post(route('almacen.compras.store'), pedidoDeCompra($lineas, $extra))
        ->assertSessionHasNoErrors();

    $vale = ValeCompra::query()->latest('id')->firstOrFail();

    actingAs(actorConRol('gerencia'))
        ->post(route('almacen.compras.aprobar', $vale))
        ->assertSessionHasNoErrors();

    return $vale->refresh();
}

/**
 * La compra tal como la registra el almacén: factura con precios con IGV,
 * así el total del comprobante es la suma de las líneas.
 *
 * @param  list<array{0: Articulo, 1: float, 2: float}>  $lineas
 * @return array<string, mixed>
 */
function compraConVale(?ValeCompra $vale, array $lineas, array $extra = []): array
{
    return [
        'fecha' => '2026-11-09',
        'fecha_emision' => '2026-11-09',
        'proveedor_id' => $vale?->proveedor_id ?? Proveedor::factory()->create()->id,
        'vale_compra_id' => $vale?->id,
        'tipo_comprobante' => 'factura',
        'serie_numero' => 'F001-'.fake()->unique()->numerify('#####'),
        'moneda' => 'PEN',
        'precios_con_igv' => true,
        'total_comprobante' => array_sum(array_map(fn (array $linea): float => round($linea[1] * $linea[2], 2), $lineas)),
        'comprobante' => documentoDePrueba('factura.pdf'),
        'detalles' => array_map(fn (array $linea): array => [
            'articulo_id' => $linea[0]->id,
            'cantidad' => $linea[1],
            'precio_unitario' => $linea[2],
        ], $lineas),
        ...$extra,
    ];
}

function almacenDePrueba(): User
{
    return actorConRol('almacen');
}

// ─── Pedir ──────────────────────────────────────────────────────────────────

it('asks a normal purchase for a quotation or another way the price was checked', function (): void {
    $filtro = Articulo::factory()->create();

    actingAs(actorConRol('taller'))
        ->post(route('almacen.compras.store'), pedidoDeCompra([[$filtro, 4, 59]], ['sustento_precio' => null]))
        ->assertSessionHasErrors('sustento_precio');

    expect(ValeCompra::query()->count())->toBe(0);

    actingAs(actorConRol('taller'))
        ->post(route('almacen.compras.store'), pedidoDeCompra([[$filtro, 4, 59]], [
            'sustento_precio' => null,
            'cotizaciones' => [documentoDePrueba('cotizacion.pdf')],
        ]))
        ->assertSessionHasNoErrors();

    $vale = ValeCompra::query()->sole();

    expect($vale->estado)->toBe(EstadoValeCompra::Solicitado)
        ->and($vale->situacion())->toBe(SituacionValeCompra::PorAprobar)
        ->and((float) $vale->total)->toBe(236.0)
        ->and($vale->getMedia('cotizaciones'))->toHaveCount(1)
        ->and($vale->detalles()->sole()->descripcion)->toBe($filtro->nombre);
});

it('lets an emergency skip the quotation but asks which unit it is for', function (): void {
    $llanta = Articulo::factory()->create();
    $datos = pedidoDeCompra([[$llanta, 1, 1450]], ['tipo' => 'emergencia', 'sustento_precio' => null]);

    actingAs(actorConRol('operaciones'))
        ->post(route('almacen.compras.store'), $datos)
        ->assertSessionHasErrors('vehiculo_id');

    actingAs(actorConRol('operaciones'))
        ->post(route('almacen.compras.store'), [...$datos, 'vehiculo_id' => Vehiculo::factory()->create()->id])
        ->assertSessionHasNoErrors();

    expect(ValeCompra::query()->sole()->vehiculo_id)->not->toBeNull();
});

it('keeps the purchase requests away from who has no permission', function (): void {
    actingAs(actorConRol('rrhh'))->get(route('almacen.compras.index'))->assertForbidden();
    actingAs(actorConRol('gerencia'))->get(route('almacen.compras.create'))->assertForbidden();
});

// ─── Aprobar ────────────────────────────────────────────────────────────────

it('is approved by gerencia, signed, and valid for five days', function (): void {
    $filtro = Articulo::factory()->create();
    actingAs(actorConRol('taller'))->post(route('almacen.compras.store'), pedidoDeCompra([[$filtro, 4, 59]]));
    $vale = ValeCompra::query()->sole();

    actingAs(actorConRol('taller'))->post(route('almacen.compras.aprobar', $vale))->assertForbidden();
    actingAs(actorConRol('operaciones'))->post(route('almacen.compras.aprobar', $vale))->assertForbidden();

    $gerente = actorConRol('gerencia');
    actingAs($gerente)->post(route('almacen.compras.aprobar', $vale))->assertSessionHasNoErrors();

    $vale->refresh();

    expect($vale->estado)->toBe(EstadoValeCompra::Aprobado)
        ->and($vale->aprobado_por)->toBe($gerente->id)
        ->and($vale->vence_el->toDateString())->toBe('2026-11-14')
        ->and($vale->firma)->not->toBeNull()
        ->and(app(FirmaDeVales::class)->esAutentico($vale))->toBeTrue()
        ->and($vale->situacion())->toBe(SituacionValeCompra::Vigente);
});

it('does not let anyone approve their own request', function (): void {
    $admin = actorConRol('admin');
    actingAs($admin)->post(route('almacen.compras.store'), pedidoDeCompra([[Articulo::factory()->create(), 1, 10]]));
    $vale = ValeCompra::query()->sole();

    actingAs($admin)
        ->post(route('almacen.compras.aprobar', $vale))
        ->assertSessionHasErrors('firma');

    expect($vale->refresh()->estado)->toBe(EstadoValeCompra::Solicitado);
});

it('lets the head of operaciones approve an emergency', function (): void {
    actingAs(actorConRol('taller'))->post(route('almacen.compras.store'), pedidoDeCompra(
        [[Articulo::factory()->create(), 1, 1450]],
        ['tipo' => 'emergencia', 'sustento_precio' => null, 'vehiculo_id' => Vehiculo::factory()->create()->id],
    ));
    $vale = ValeCompra::query()->sole();

    actingAs(actorConRol('operaciones'))
        ->post(route('almacen.compras.aprobar', $vale))
        ->assertSessionHasNoErrors();

    expect($vale->refresh()->estado)->toBe(EstadoValeCompra::Aprobado);
});

it('does not approve without a signing key on the server', function (): void {
    config(['transpaty.compras.clave_firma' => null]);
    actingAs(actorConRol('taller'))->post(route('almacen.compras.store'), pedidoDeCompra([[Articulo::factory()->create(), 1, 10]]));
    $vale = ValeCompra::query()->sole();

    actingAs(actorConRol('gerencia'))
        ->post(route('almacen.compras.aprobar', $vale))
        ->assertSessionHasErrors('firma');

    expect($vale->refresh()->estado)->toBe(EstadoValeCompra::Solicitado);
});

it('rejects with a reason, and a corrected request goes back to approval without its signature', function (): void {
    $filtro = Articulo::factory()->create();
    $vale = valeAprobado([[$filtro, 4, 59]]);

    actingAs(actorConRol('taller'))
        ->put(route('almacen.compras.update', $vale), pedidoDeCompra([[$filtro, 4, 65]], ['proveedor_id' => $vale->proveedor_id]))
        ->assertSessionHasNoErrors();

    $vale->refresh();

    expect($vale->estado)->toBe(EstadoValeCompra::Solicitado)
        ->and($vale->firma)->toBeNull()
        ->and($vale->vence_el)->toBeNull()
        ->and((float) $vale->total)->toBe(260.0);

    actingAs(actorConRol('gerencia'))
        ->post(route('almacen.compras.rechazar', $vale), ['motivo' => 'El precio subió demasiado'])
        ->assertSessionHasNoErrors();

    expect($vale->refresh()->situacion())->toBe(SituacionValeCompra::Rechazado)
        ->and($vale->motivo_rechazo)->toBe('El precio subió demasiado');
});

// ─── Verificar ──────────────────────────────────────────────────────────────

it('shows the real vale to whoever has its QR, without logging in', function (): void {
    $vale = valeAprobado([[Articulo::factory()->create(['nombre' => 'LLANTA 295/80R22.5']), 2, 1450]]);
    auth()->logout();

    get(route('vales.verificar', $vale->codigo_publico))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('publico/verificar-vale')
            ->where('vale.numero', $vale->numero())
            ->where('vale.valido', true)
            ->where('vale.firma_valida', true)
            ->where('vale.total', 2900)
            ->where('vale.detalles.0.descripcion', 'LLANTA 295/80R22.5')
            ->where('vale.aprobado_por', $vale->aprobador->name)
            ->where('vale.vence_el', '2026-11-14'));

    expect(ConsultaValeCompra::query()->sole()->situacion)->toBe(SituacionValeCompra::Vigente);
});

it('does not validate a vale that was changed after it was signed', function (): void {
    $vale = valeAprobado([[Articulo::factory()->create(), 2, 1450]]);

    // Alguien sube el monto directo en la base.
    DB::table('detalles_vale_compra')->where('vale_compra_id', $vale->id)->update(['cantidad' => 4, 'total' => 5800]);
    DB::table('vales_compra')->where('id', $vale->id)->update(['total' => 5800]);
    auth()->logout();

    get(route('vales.verificar', $vale->codigo_publico))
        ->assertInertia(fn (Assert $page) => $page
            ->where('vale.firma_valida', false)
            ->where('vale.valido', false));
});

it('finds the vale by the code typed by hand and says so when there is none', function (): void {
    $vale = valeAprobado([[Articulo::factory()->create(), 1, 10]]);
    auth()->logout();

    post(route('vales.verificar.buscar'), ['codigo' => ' '.mb_strtolower(str_replace('-', ' ', $vale->codigo_verificacion))])
        ->assertRedirect(route('vales.verificar', $vale->codigo_publico));

    post(route('vales.verificar.buscar'), ['codigo' => 'AAAA-BBBB-CCCC'])
        ->assertSessionHasErrors('codigo');

    get(route('vales.verificar', 'no-existe'))
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->where('vale', null)->where('buscado', true));
});

it('says a vale is not valid before approval, once expired and once voided', function (): void {
    actingAs(actorConRol('taller'))->post(route('almacen.compras.store'), pedidoDeCompra([[Articulo::factory()->create(), 1, 10]]));
    $vale = ValeCompra::query()->sole();

    $valido = fn (): bool => get(route('vales.verificar', $vale->codigo_publico))->viewData('page')['props']['vale']['valido'];

    expect($valido())->toBeFalse();

    actingAs(actorConRol('gerencia'))->post(route('almacen.compras.aprobar', $vale));
    expect($valido())->toBeTrue();

    // Vale hasta el 14 inclusive.
    $this->travelTo(Carbon::parse('2026-11-14 17:00:00'));
    expect($valido())->toBeTrue();

    $this->travelTo(Carbon::parse('2026-11-15 17:00:00'));
    expect($valido())->toBeFalse()
        ->and($vale->refresh()->situacion())->toBe(SituacionValeCompra::Vencido);

    $this->travelTo(Carbon::parse('2026-11-10 17:00:00'));
    actingAs(actorConRol('gerencia'))->post(route('almacen.compras.anular', $vale), ['motivo' => 'Ya no hace falta'])->assertForbidden();
    actingAs(actorConRol('admin'))->post(route('almacen.compras.anular', $vale), ['motivo' => 'Ya no hace falta'])->assertSessionHasNoErrors();

    expect($valido())->toBeFalse()
        ->and($vale->refresh()->situacion())->toBe(SituacionValeCompra::Anulado);
});

// ─── La compra entra con su vale ────────────────────────────────────────────

it('does not take a purchase into the warehouse without an approved vale', function (): void {
    $filtro = Articulo::factory()->create();

    actingAs(almacenDePrueba())
        ->post(route('almacen.ingresos.store'), compraConVale(null, [[$filtro, 4, 59]]))
        ->assertSessionHasErrors('vale_compra_id');

    actingAs(actorConRol('taller'))->post(route('almacen.compras.store'), pedidoDeCompra([[$filtro, 4, 59]]));
    $sinAprobar = ValeCompra::query()->sole();

    actingAs(almacenDePrueba())
        ->post(route('almacen.ingresos.store'), compraConVale($sinAprobar, [[$filtro, 4, 59]]))
        ->assertSessionHasErrors('vale_compra_id');

    expect(IngresoAlmacen::query()->count())->toBe(0);
});

it('takes the purchase with its vale, and the vale is used only once', function (): void {
    $filtro = Articulo::factory()->create();
    $vale = valeAprobado([[$filtro, 4, 59]]);

    actingAs(almacenDePrueba())
        ->post(route('almacen.ingresos.store'), compraConVale($vale, [[$filtro, 4, 59]]))
        ->assertSessionHasNoErrors();

    expect(IngresoAlmacen::query()->sole()->vale_compra_id)->toBe($vale->id)
        ->and((float) $filtro->refresh()->stock)->toBe(4.0)
        ->and($vale->refresh()->situacion())->toBe(SituacionValeCompra::Usado);

    actingAs(almacenDePrueba())
        ->post(route('almacen.ingresos.store'), compraConVale($vale, [[$filtro, 4, 59]]))
        ->assertSessionHasErrors('vale_compra_id');

    expect(IngresoAlmacen::query()->count())->toBe(1);

    get(route('vales.verificar', $vale->codigo_publico))
        ->assertInertia(fn (Assert $page) => $page->where('vale.valido', false)->where('vale.situacion', 'usado'));
});

it('sends any difference with the approved amount back to approval', function (): void {
    $filtro = Articulo::factory()->create();
    $otro = Articulo::factory()->create();
    $vale = valeAprobado([[$filtro, 4, 59]]);

    $rechaza = fn (array $datos) => actingAs(almacenDePrueba())
        ->post(route('almacen.ingresos.store'), $datos)
        ->assertSessionHasErrors('vale_compra_id');

    // Diez céntimos más por unidad.
    $rechaza(compraConVale($vale, [[$filtro, 4, 59.10]]));
    // Menos cantidad: el monto ya no es el aprobado.
    $rechaza(compraConVale($vale, [[$filtro, 3, 59]]));
    // Un artículo que el vale no autoriza.
    $rechaza(compraConVale($vale, [[$otro, 4, 59]]));
    // Otro proveedor.
    $rechaza(compraConVale($vale, [[$filtro, 4, 59]], ['proveedor_id' => Proveedor::factory()->create()->id]));
    // Un comprobante de antes de la aprobación.
    $rechaza(compraConVale($vale, [[$filtro, 4, 59]], ['fecha_emision' => '2026-11-08']));

    expect(IngresoAlmacen::query()->count())->toBe(0)
        ->and(session('errors')->first('vale_compra_id'))->toContain('antes de que');
});

it('does not take a comprobante issued after the vale expired', function (): void {
    $filtro = Articulo::factory()->create();
    $vale = valeAprobado([[$filtro, 4, 59]]);

    $this->travelTo(Carbon::parse('2026-11-16 17:00:00'));

    actingAs(almacenDePrueba())
        ->post(route('almacen.ingresos.store'), compraConVale($vale, [[$filtro, 4, 59]], ['fecha' => '2026-11-16', 'fecha_emision' => '2026-11-15']))
        ->assertSessionHasErrors('vale_compra_id');

    // Comprado el último día de vigencia e ingresado después: entra.
    actingAs(almacenDePrueba())
        ->post(route('almacen.ingresos.store'), compraConVale($vale, [[$filtro, 4, 59]], ['fecha' => '2026-11-16', 'fecha_emision' => '2026-11-14']))
        ->assertSessionHasNoErrors();

    expect($vale->refresh()->situacion())->toBe(SituacionValeCompra::Usado);
});

it('does not take a purchase with a vale that was tampered with', function (): void {
    $filtro = Articulo::factory()->create();
    $vale = valeAprobado([[$filtro, 4, 59]]);

    DB::table('detalles_vale_compra')->where('vale_compra_id', $vale->id)->update(['precio_unitario' => 90, 'total' => 360]);
    DB::table('vales_compra')->where('id', $vale->id)->update(['total' => 360]);

    actingAs(almacenDePrueba())
        ->post(route('almacen.ingresos.store'), compraConVale($vale, [[$filtro, 4, 90]]))
        ->assertSessionHasErrors('vale_compra_id');

    expect(session('errors')->first('vale_compra_id'))->toContain('modificado');
});

it('frees the vale again when its purchase is voided', function (): void {
    $filtro = Articulo::factory()->create();
    $vale = valeAprobado([[$filtro, 4, 59]]);
    actingAs(almacenDePrueba())->post(route('almacen.ingresos.store'), compraConVale($vale, [[$filtro, 4, 59]]));

    actingAs(actorConRol('admin'))->post(route('almacen.compras.anular', $vale), ['motivo' => 'Se anula el vale'])->assertForbidden();

    actingAs(actorConRol('admin'))
        ->post(route('almacen.ingresos.anular', IngresoAlmacen::query()->sole()), ['motivo' => 'Se registró dos veces'])
        ->assertSessionHasNoErrors();

    expect($vale->refresh()->situacion())->toBe(SituacionValeCompra::Vigente);
});

// ─── Entregas parciales ─────────────────────────────────────────────────────

it('keeps partial deliveries hidden and for gerencia or the admin to turn on', function (): void {
    $filtro = Articulo::factory()->create();
    $vale = valeAprobado([[$filtro, 4, 59]]);

    actingAs(actorConRol('taller'))
        ->post(route('almacen.compras.parciales', $vale), ['permite_parciales' => true])
        ->assertForbidden();

    actingAs(actorConRol('taller'))
        ->get(route('almacen.compras.show', $vale))
        ->assertInertia(fn (Assert $page) => $page->where('vale.permite_parciales', null)->where('puede.parciales', false));

    actingAs(actorConRol('gerencia'))
        ->post(route('almacen.compras.parciales', $vale), ['permite_parciales' => true])
        ->assertSessionHasNoErrors();

    actingAs(actorConRol('gerencia'))
        ->get(route('almacen.compras.show', $vale))
        ->assertInertia(fn (Assert $page) => $page->where('vale.permite_parciales', true)->where('puede.parciales', true));

    // Con parciales entra en dos compras, sin pasarse de lo aprobado.
    actingAs(almacenDePrueba())
        ->post(route('almacen.ingresos.store'), compraConVale($vale, [[$filtro, 1, 59]]))
        ->assertSessionHasNoErrors();

    expect($vale->refresh()->situacion())->toBe(SituacionValeCompra::Parcial)
        ->and($vale->saldo())->toBe(177.0);

    actingAs(almacenDePrueba())
        ->post(route('almacen.ingresos.store'), compraConVale($vale, [[$filtro, 4, 59]]))
        ->assertSessionHasErrors('vale_compra_id');

    actingAs(almacenDePrueba())
        ->post(route('almacen.ingresos.store'), compraConVale($vale, [[$filtro, 3, 59]]))
        ->assertSessionHasNoErrors();

    expect($vale->refresh()->situacion())->toBe(SituacionValeCompra::Usado);
});

// ─── Servicios de terceros ──────────────────────────────────────────────────

it('charges a third-party service to its orden once it is confirmed with its comprobante', function (): void {
    $orden = OrdenTrabajo::factory()->create(['estado' => 'en_proceso']);
    $vale = valeAprobado([['Rectificado de culata', 1, 850]], ['orden_trabajo_id' => $orden->id]);
    $servicio = $vale->detalles()->sole();

    expect($vale->vehiculo_id)->toBe($orden->vehiculo_id)
        ->and(app(MaterialesDeOrden::class)->costo($orden))->toBe(0.0);

    actingAs(actorConRol('taller'))
        ->post(route('almacen.compras.servicios.confirmar', [$vale, $servicio]), [])
        ->assertSessionHasErrors('comprobante');

    actingAs(actorConRol('taller'))
        ->post(route('almacen.compras.servicios.confirmar', [$vale, $servicio]), ['comprobante' => documentoDePrueba('factura-servicio.pdf')])
        ->assertSessionHasNoErrors();

    expect(app(MaterialesDeOrden::class)->costo($orden))->toBe(850.0)
        ->and($vale->refresh()->situacion())->toBe(SituacionValeCompra::Usado)
        ->and($vale->getMedia('comprobantes_servicio'))->toHaveCount(1);

    actingAs(actorConRol('admin'))
        ->get(route('mantenimiento.ordenes.edit', $orden))
        ->assertInertia(fn (Assert $page) => $page
            ->where('materiales.costo', 850)
            ->where('materiales.terceros.0.descripcion', 'Rectificado de culata')
            ->where('materiales.terceros.0.atendido', true));
});

it('asks a service line for its description and keeps those vales in soles', function (): void {
    actingAs(actorConRol('taller'))
        ->post(route('almacen.compras.store'), pedidoDeCompra([['', 1, 850]]))
        ->assertSessionHasErrors('detalles.0.descripcion');

    actingAs(actorConRol('taller'))
        ->post(route('almacen.compras.store'), pedidoDeCompra([['Rectificado de culata', 1, 250]], ['moneda' => 'USD']))
        ->assertSessionHasErrors('moneda');
});

// ─── El vale impreso ────────────────────────────────────────────────────────

it('prints the approved vale as a PDF, and not before it is approved', function (): void {
    actingAs(actorConRol('taller'))->post(route('almacen.compras.store'), pedidoDeCompra([[Articulo::factory()->create(), 2, 1450]]));
    $vale = ValeCompra::query()->sole();

    actingAs(actorConRol('taller'))->get(route('almacen.compras.pdf', $vale))->assertNotFound();

    actingAs(actorConRol('gerencia'))->post(route('almacen.compras.aprobar', $vale));

    $respuesta = actingAs(actorConRol('taller'))->get(route('almacen.compras.pdf', $vale))->assertSuccessful();

    expect($respuesta->headers->get('content-type'))->toBe('application/pdf')
        ->and($respuesta->getContent())->toStartWith('%PDF');
});

it('lists the vales and opens the request form from an orden', function (): void {
    $orden = OrdenTrabajo::factory()->create(['estado' => 'en_proceso']);
    valeAprobado([[Articulo::factory()->create(), 2, 1450]]);

    actingAs(actorConRol('taller'))
        ->get(route('almacen.compras.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('almacen/compras/index')
            ->has('vales.data', 1)
            ->where('vales.data.0.situacion', 'vigente')
            ->where('porAprobar', 0));

    actingAs(actorConRol('taller'))
        ->get(route('almacen.compras.create', ['orden' => $orden->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('almacen/compras/form')
            ->where('inicial.orden_trabajo_id', $orden->id)
            ->where('inicial.vehiculo_id', $orden->vehiculo_id));
});
