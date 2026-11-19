<?php

use App\Enums\TipoAlerta;
use App\Models\Articulo;
use App\Models\CuentaBancaria;
use App\Models\Proveedor;
use App\Models\User;
use App\Models\ValeCompra;
use App\Notifications\Alerta;
use App\Services\FirmaDeVales;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

/*
 * El pago de una compra: el pedido dice cómo se paga, gerencia se entera
 * por la alerta y lo aprueba desde el enlace, y quien hace la transferencia
 * anota el pago con su voucher. Transpaty no mueve dinero: solo lo anota.
 */
beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-11-19 17:00:00'));
    Storage::fake('public');
    Storage::fake('local');

    config(['transpaty.compras.clave_firma' => app(FirmaDeVales::class)->generarClaves()['privada']]);

    $this->almacen = actorConRol('almacen');
    $this->gerencia = actorConRol('gerencia');
    $this->contador = actorConRol('contador');
    $this->proveedor = Proveedor::factory()->create(['cuenta_pago' => 'BCP soles 215-1234567-0-12']);
});

/** Un pedido de compra por S/ 400, pedido por el almacén. */
function pedidoDe400(User $quien, Proveedor $proveedor, array $extra = []): ValeCompra
{
    actingAs($quien)
        ->post(route('almacen.compras.store'), [
            'tipo' => 'normal',
            'proveedor_id' => $proveedor->id,
            'moneda' => 'PEN',
            'motivo' => 'Reposición de filtros',
            'sustento_precio' => 'Precio de lista del proveedor.',
            'detalles' => [['es_servicio' => false, 'articulo_id' => Articulo::factory()->create()->id, 'cantidad' => 4, 'precio_unitario' => 100]],
            ...$extra,
        ])
        ->assertSessionHasNoErrors();

    return ValeCompra::query()->latest('id')->firstOrFail();
}

function voucherDePrueba(): UploadedFile
{
    return UploadedFile::fake()->image('voucher.jpg', 600, 800);
}

it('tells gerencia there is an order to approve, with the link to it', function (): void {
    Notification::fake();

    $pedido = pedidoDe400($this->almacen, $this->proveedor);

    Notification::assertSentTo(
        $this->gerencia,
        Alerta::class,
        fn (Alerta $alerta): bool => $alerta->tipo === TipoAlerta::PedidoPorAprobar
            && str_contains($alerta->titulo, $pedido->numero())
            && str_contains($alerta->titulo, '400.00')
            && $alerta->url === route('almacen.compras.show', $pedido),
    );
    // Quien pidió no recibe su propio aviso.
    Notification::assertNotSentTo($this->almacen, Alerta::class);

    // Al aprobarse, quien compra y contabilidad se enteran.
    actingAs($this->gerencia)->post(route('almacen.compras.aprobar', $pedido))->assertSessionHasNoErrors();

    Notification::assertSentTo($this->almacen, Alerta::class, fn (Alerta $alerta): bool => $alerta->tipo === TipoAlerta::PedidoResuelto);
    Notification::assertSentTo($this->contador, Alerta::class, fn (Alerta $alerta): bool => $alerta->tipo === TipoAlerta::PedidoResuelto);
});

it('only lets gerencia approve from the link', function (): void {
    $pedido = pedidoDe400($this->almacen, $this->proveedor);

    // Sin sesión, el enlace lleva a iniciar sesión.
    auth()->logout();
    $this->get(route('almacen.compras.show', $pedido))->assertRedirect(route('login'));

    actingAs($this->contador)
        ->get(route('almacen.compras.show', $pedido))
        ->assertInertia(fn (Assert $page) => $page->where('puede.aprobar', false));
    actingAs($this->contador)->post(route('almacen.compras.aprobar', $pedido))->assertForbidden();

    actingAs($this->gerencia)
        ->get(route('almacen.compras.show', $pedido))
        ->assertInertia(fn (Assert $page) => $page->where('puede.aprobar', true)->where('vale.pago', null));
});

it('records a cash-down purchase as paid with its voucher', function (): void {
    $pedido = pedidoDe400($this->almacen, $this->proveedor);
    $cuenta = CuentaBancaria::factory()->create();
    $pago = fn (array $extra = []): array => [
        'fecha' => '2026-11-19', 'monto' => 400, 'medio' => 'transferencia', 'cuenta_bancaria_id' => $cuenta->id,
        'numero_operacion' => '00458712', 'voucher' => voucherDePrueba(), ...$extra,
    ];

    // Sin aprobar no hay nada que pagar.
    actingAs($this->gerencia)->post(route('almacen.compras.pagos.store', $pedido), $pago())->assertForbidden();

    actingAs($this->gerencia)->post(route('almacen.compras.aprobar', $pedido))->assertSessionHasNoErrors();

    actingAs($this->gerencia)
        ->get(route('almacen.compras.show', $pedido))
        ->assertInertia(fn (Assert $page) => $page
            ->where('puede.pagar', true)
            ->where('vale.forma_pago', 'Contado')
            ->where('vale.pago.por_pagar', 400)
            ->where('vale.pago.vence_el', '2026-11-19')
            ->where('vale.pago.cuenta_proveedor', 'BCP soles 215-1234567-0-12'));

    // Quien pidió no paga; una transferencia lleva operación y voucher; no se paga de más.
    actingAs($this->almacen)->post(route('almacen.compras.pagos.store', $pedido), $pago())->assertForbidden();
    actingAs($this->gerencia)->post(route('almacen.compras.pagos.store', $pedido), $pago(['numero_operacion' => null, 'voucher' => null]))->assertSessionHasErrors(['numero_operacion', 'voucher']);
    actingAs($this->gerencia)->post(route('almacen.compras.pagos.store', $pedido), $pago(['monto' => 450]))->assertSessionHasErrors('monto');

    actingAs($this->gerencia)->post(route('almacen.compras.pagos.store', $pedido), $pago())->assertSessionHasNoErrors();

    expect($pedido->refresh()->pagado())->toBe(400.0)
        ->and($pedido->tienePagoPendiente())->toBeFalse()
        ->and($pedido->pagos()->sole()->voucher_id)->not->toBeNull();

    actingAs($this->contador)
        ->get(route('almacen.compras.show', $pedido))
        ->assertInertia(fn (Assert $page) => $page
            ->where('vale.pago.por_pagar', 0)
            ->where('vale.pago.pagos.0.numero_operacion', '00458712')
            ->where('vale.pago.pagos.0.registro', $this->gerencia->name));
});

it('pays a credit purchase in parts, by its due date, and voids a wrong payment', function (): void {
    $pedido = pedidoDe400($this->almacen, $this->proveedor, ['forma_pago' => 'credito', 'dias_credito' => 30]);
    actingAs($this->gerencia)->post(route('almacen.compras.aprobar', $pedido))->assertSessionHasNoErrors();

    expect($pedido->refresh()->pagoVenceEl()?->toDateString())->toBe('2026-12-19');

    // Contabilidad anota un adelanto en efectivo: sin operación ni voucher.
    actingAs($this->contador)
        ->post(route('almacen.compras.pagos.store', $pedido), ['fecha' => '2026-11-19', 'monto' => 150, 'medio' => 'efectivo'])
        ->assertSessionHasNoErrors();

    expect($pedido->refresh()->porPagar())->toBe(250.0)->and($pedido->tienePagoPendiente())->toBeTrue();

    // Aparece en «Por pagar» de la portada, para quien paga.
    actingAs($this->contador)
        ->get(route('almacen.compras.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('porPagar.0.id', $pedido->id)
            ->where('porPagar.0.por_pagar', 250)
            ->where('porPagar.0.vence_el', '2026-12-19'));

    // Se anotó mal: se anula con su motivo y vuelve a deberse todo.
    actingAs($this->contador)
        ->post(route('almacen.compras.pagos.anular', [$pedido, $pedido->pagos()->sole()]), ['motivo' => 'Era de otro pedido'])
        ->assertSessionHasNoErrors();

    expect($pedido->refresh()->porPagar())->toBe(400.0);

    actingAs($this->almacen)
        ->post(route('almacen.compras.store'), [
            'tipo' => 'normal', 'proveedor_id' => $this->proveedor->id, 'moneda' => 'PEN', 'motivo' => 'Otra compra a crédito',
            'sustento_precio' => 'Precio de lista del proveedor.', 'forma_pago' => 'credito',
            'detalles' => [['es_servicio' => false, 'articulo_id' => Articulo::factory()->create()->id, 'cantidad' => 1, 'precio_unitario' => 10]],
        ])
        ->assertSessionHasErrors('dias_credito');
});
