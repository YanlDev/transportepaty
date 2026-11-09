<?php

use App\Enums\TipoVehiculo;
use App\Models\Articulo;
use App\Models\IngresoAlmacen;
use App\Models\InventarioAlmacen;
use App\Models\Proveedor;
use App\Models\SalidaAlmacen;
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
 * Una compra con factura entra al stock sin IGV.
 */
it('registra una compra desde la pantalla', function (): void {
    Articulo::factory()->create(['nombre' => 'PASTILLA DE FRENO']);
    Proveedor::factory()->create(['razon_social' => 'REPUESTOS DEL SUR SAC']);

    actingAs(actorConRol('almacen'));

    visit('/almacen/ingresos/crear')
        ->on()->desktop()
        ->click('#ingreso-proveedor')
        ->click('REPUESTOS DEL SUR SAC')
        ->type('input[placeholder="F001-00012345"]', 'F001-777')
        ->click('[aria-label="Artículo"]')
        ->click('PASTILLA DE FRENO')
        ->type('[aria-label="Cantidad"]', '4')
        ->type('[aria-label="Precio unitario"]', '100')
        ->assertSee('472.00')
        ->click('button[type="submit"]:has-text("Registrar compra")')
        ->wait(1)
        ->assertSee('Compra IN-')
        ->assertNoJavaScriptErrors()
        ->screenshot(fullPage: true, filename: 'almacen-ingreso');

    expect((float) IngresoAlmacen::query()->sole()->costo_total)->toBe(400.0);
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
