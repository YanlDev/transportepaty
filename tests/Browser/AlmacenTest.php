<?php

use App\Enums\TipoVehiculo;
use App\Models\Articulo;
use App\Models\InventarioAlmacen;
use App\Models\Proveedor;
use App\Models\SalidaAlmacen;
use App\Models\UbicacionAlmacen;
use App\Models\Vehiculo;
use App\Services\Kardex;

use function Pest\Laravel\actingAs;

/**
 * El arranque del almacén sin Excel: se abre un conteo, se anota lo que hay
 * en el estante y al cerrarlo el stock queda como se contó.
 */
it('arranca el stock con un conteo desde la pantalla', function (): void {
    $filtro = Articulo::factory()->create(['nombre' => 'FILTRO DE ACEITE DC13']);

    actingAs(actorConRol('almacen'));

    visit('/almacen/inventarios')
        ->on()->desktop()
        ->click('Abrir conteo')
        ->wait(1)
        ->assertSee('Anotar')
        ->click('[aria-label="Artículo"]')
        ->click('FILTRO DE ACEITE DC13')
        ->type('[aria-label="Cantidad contada"]', '8')
        ->type('[aria-label="Costo unitario"]', '45.50')
        ->click('button[type="submit"]:has-text("Anotar")')
        ->assertSee('8 UND')
        ->click('Cerrar conteo')
        ->assertSee('Cerrado')
        ->assertNoJavaScriptErrors()
        ->screenshot(fullPage: true, filename: 'almacen-conteo');

    expect((float) $filtro->refresh()->stock)->toBe(8.0)
        ->and((float) $filtro->valor)->toBe(364.0);
});

/**
 * La compra se llena en la pantalla: el total cuadra con los ítems y el
 * PDF queda adjunto.
 */
it('registra una compra desde la pantalla', function (): void {
    Articulo::factory()->create(['nombre' => 'PASTILLA DE FRENO']);
    Proveedor::factory()->create(['razon_social' => 'REPUESTOS DEL SUR SAC']);

    actingAs(actorConRol('almacen'));

    $pagina = visit('/almacen/ingresos/crear')
        ->on()->desktop()
        ->click('#ingreso-proveedor')
        ->click('REPUESTOS DEL SUR SAC')
        ->type('input[placeholder="F001-00012345"]', 'F001-777')
        ->click('[aria-label="Artículo"]')
        ->click('PASTILLA DE FRENO')
        ->type('[aria-label="Cantidad"]', '4')
        ->type('[aria-label="Precio unitario"]', '100')
        ->type('[aria-label="Total del comprobante"]', '472')
        ->assertSee('Cuadra con los ítems');

    // El servidor de Playwright no adjunta archivos del disco: el PDF se arma
    // en la página y se le pasa al campo, como si se hubiera elegido.
    $pagina->script(<<<'JS'
        (() => {
            const pdf = new File(['%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n'], 'factura-777.pdf', { type: 'application/pdf' });
            const transferencia = new DataTransfer();
            transferencia.items.add(pdf);
            const campo = document.querySelector('input[type="file"]:not([capture])');
            campo.files = transferencia.files;
            campo.dispatchEvent(new Event('change', { bubbles: true }));
            return true;
        })()
        JS);

    // El envío no se prueba acá: el servidor del plugin de navegador todavía
    // no pasa archivos (`@TODO files` en LaravelHttpServer). El registro con
    // su PDF lo cubren las pruebas de `Feature/AlmacenTest`.
    $pagina
        ->assertSee('factura-777.pdf')
        ->assertSee('Entra al kardex')
        ->assertNoJavaScriptErrors()
        ->screenshot(fullPage: true, filename: 'almacen-ingreso');
});

/**
 * Un vale saca del stock hacia una unidad, al costo promedio.
 */
it('emite un vale de salida desde la pantalla', function (): void {
    $filtro = Articulo::factory()->create(['nombre' => 'PASTILLA DE FRENO']);
    $conteo = InventarioAlmacen::factory()->create();
    $conteo->detalles()->create(['articulo_id' => $filtro->id, 'cantidad_contada' => 4, 'costo_unitario' => 100]);
    app(Kardex::class)->registrarInventario($conteo);
    Vehiculo::factory()->create(['tipo' => TipoVehiculo::Tracto, 'placa' => 'ALM-001']);

    actingAs(actorConRol('almacen'));

    visit('/almacen/salidas/crear')
        ->on()->desktop()
        ->click('[aria-label="Placa"]')
        ->click('[cmdk-item]:has-text("ALM001")')
        ->click('[aria-label="Artículo"]')
        ->click('[cmdk-item]:has-text("PASTILLA DE FRENO")')
        ->type('[aria-label="Cantidad"]', '1')
        ->click('Emitir vale')
        ->wait(1)
        ->assertSee('Vale VS-')
        ->assertNoJavaScriptErrors()
        ->screenshot(fullPage: true, filename: 'almacen-vale');

    expect((float) SalidaAlmacen::query()->sole()->costo_total)->toBe(100.0)
        ->and((float) $filtro->refresh()->stock)->toBe(3.0);
});

/**
 * La configuración: un estante nuevo y los mínimos en bloque.
 */
it('configura estantes y mínimos desde la pantalla', function (): void {
    $filtro = Articulo::factory()->create(['nombre' => 'FILTRO DE AIRE']);

    actingAs(actorConRol('almacen'));

    visit('/almacen/configuracion')
        ->on()->desktop()
        ->click('button:has-text("Ubicaciones")')
        ->type('input[placeholder^="Nuevo estante"]', 'A-1')
        ->click('button[type="submit"]:has-text("Agregar")')
        ->wait(1)
        ->assertSee('Sin artículos')
        ->click('button:has-text("Mínimos")')
        ->type('[aria-label="Mínimo de FILTRO DE AIRE"]', '6')
        ->click('button:has-text("Guardar 1 mínimo")')
        ->wait(1)
        ->assertSee('Sin cambios')
        ->assertNoJavaScriptErrors()
        ->screenshot(fullPage: true, filename: 'almacen-configuracion');

    expect((float) $filtro->refresh()->stock_minimo)->toBe(6.0)
        ->and(UbicacionAlmacen::query()->sole()->nombre)->toBe('A-1');
});

/**
 * El proveedor que se crea desde la compra queda elegido: no hay que ir a
 * buscarlo a la lista.
 */
it('deja elegido el proveedor que se crea desde la compra', function (): void {
    Proveedor::factory()->create(['razon_social' => 'YA EXISTIA SAC']);

    actingAs(actorConRol('almacen'));

    visit('/almacen/ingresos/crear')
        ->on()->desktop()
        ->click('[aria-label="Proveedor nuevo"]')
        ->type('input[placeholder="20123456789"]', '20555666777')
        ->type('[role="dialog"] input >> nth=1', 'nuevo proveedor sac')
        ->click('[role="dialog"] button[type="submit"]')
        ->wait(1)
        ->assertSeeIn('#ingreso-proveedor', 'NUEVO PROVEEDOR SAC')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'almacen-proveedor-creado');
});
