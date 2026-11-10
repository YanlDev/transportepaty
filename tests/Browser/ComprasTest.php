<?php

use App\Enums\EstadoValeCompra;
use App\Models\Articulo;
use App\Models\User;
use App\Models\ValeCompra;
use App\Services\FirmaDeVales;
use App\Services\RelojOperativo;

use function Pest\Laravel\actingAs;

/**
 * El circuito de compra en pantalla: el pedido, el vale aprobado con su QR y
 * la página que abre el proveedor sin cuenta. Todo carga en el celular sin
 * errores ni scroll horizontal.
 */
beforeEach(function (): void {
    config(['transpaty.compras.clave_firma' => app(FirmaDeVales::class)->generarClaves()['privada']]);

    $llanta = Articulo::factory()->create(['nombre' => 'LLANTA 295/80R22.5 DELANTERA']);

    $this->vale = ValeCompra::factory()->create([
        'estado' => EstadoValeCompra::Aprobado,
        'total' => 2900,
        'motivo' => 'Dos llantas delanteras para el tracto',
        'aprobado_por' => User::factory()->create(['name' => 'Gerente General'])->id,
        'aprobado_at' => now()->startOfSecond(),
        'vence_el' => RelojOperativo::fechaDeHoy()->addDays(5)->toDateString(),
    ]);
    $this->vale->detalles()->create([
        'articulo_id' => $llanta->id,
        'descripcion' => $llanta->nombre,
        'cantidad' => 2,
        'precio_unitario' => 1450,
        'total' => 2900,
    ]);

    app(FirmaDeVales::class)->firmar($this->vale);
});

it('muestra el vale aprobado con su QR y su código en el celular', function (): void {
    actingAs(actorConRol('admin'));

    visit("/almacen/compras/{$this->vale->id}")
        ->on()->mobile()
        ->assertSee($this->vale->numero())
        ->assertSee('Firma válida')
        ->assertSee($this->vale->codigo_verificacion)
        ->assertSee('Registrar la compra con este vale')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'movil-compras-vale');
});

it('lista los vales y abre el formulario del pedido', function (): void {
    actingAs(actorConRol('admin'));

    visit('/almacen/compras')
        ->assertSee($this->vale->numero())
        ->assertSee('Vigente')
        ->assertNoJavaScriptErrors();

    visit('/almacen/compras/crear')
        ->on()->mobile()
        ->assertSee('Tipo de compra')
        ->assertSee('Sustento del precio')
        ->assertSee('Servicio de un tercero')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'movil-compras-pedido');
});

it('le muestra el vale real al proveedor sin iniciar sesión', function (): void {
    visit("/verificar-vale/{$this->vale->codigo_publico}")
        ->on()->mobile()
        ->assertSee('Vale válido')
        ->assertSee('LLANTA 295/80R22.5 DELANTERA')
        ->assertSee('Gerente General')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'movil-verificar-vale');
});

it('dice que no sirve cuando el vale fue alterado, y que no existe cuando no existe', function (): void {
    $this->vale->forceFill(['total' => 9900])->saveQuietly();

    visit("/verificar-vale/{$this->vale->codigo_publico}")
        ->assertSee('No válido para comprar')
        ->assertSee('fue modificado después de aprobarse')
        ->assertNoJavaScriptErrors();

    visit('/verificar-vale')
        ->assertSee('Código de verificación impreso en el vale')
        ->assertNoJavaScriptErrors();
});
