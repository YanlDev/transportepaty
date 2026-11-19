<?php

use App\Enums\EstadoOrdenTrabajo;
use App\Models\Articulo;
use App\Models\OrdenTrabajo;
use App\Models\RepuestoOrden;
use App\Models\Vehiculo;

use function Pest\Laravel\actingAs;

/**
 * La portada del almacén abre con lo que hay que hacer: las órdenes que
 * esperan repuestos y lo que hay que comprar, también en el celular.
 */
it('abre el almacén con lo que hay que hacer, sin desbordarse en celular', function (): void {
    $orden = OrdenTrabajo::factory()->create([
        'vehiculo_id' => Vehiculo::factory()->create(['placa' => 'ALM-101'])->id,
        'estado' => EstadoOrdenTrabajo::Pendiente,
        'aprobado_at' => now(),
    ]);
    $orden->mecanicos()->attach(actorConRol('mecanico')->id, ['responsable' => true]);
    RepuestoOrden::factory()->create(['orden_trabajo_id' => $orden->id, 'articulo_id' => Articulo::factory()->create(['nombre' => 'FILTRO DE AIRE'])->id, 'descripcion' => 'FILTRO DE AIRE', 'cantidad' => 2, 'aprobado_at' => now()]);
    RepuestoOrden::factory()->create(['orden_trabajo_id' => $orden->id, 'descripcion' => 'Manguera de aire de 1/2', 'aprobado_at' => now()]);

    actingAs(actorConRol('almacen'));

    visit('/almacen')
        ->on()->mobile()
        ->assertSee('Órdenes que esperan repuestos')
        ->assertSee('Repuestos por comprar')
        ->assertSee('ALM101')
        ->assertSee('Pasar al catálogo')
        ->assertSee('Pedir la compra')
        ->assertSee('Entregas')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'movil-almacen-por-hacer');

    visit("/almacen/salidas/crear?orden={$orden->id}")
        ->assertSee('Entregar repuestos')
        ->assertSee("Lo que la {$orden->numero()} tiene pendiente")
        ->assertNoJavaScriptErrors();
});
