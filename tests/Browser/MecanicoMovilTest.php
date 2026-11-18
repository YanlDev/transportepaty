<?php

use App\Enums\EstadoOrdenTrabajo;
use App\Models\OrdenTrabajo;
use App\Models\TareaOrden;
use App\Models\Vehiculo;

use function Pest\Laravel\actingAs;

/**
 * El mecánico trabaja con el celular en la mano: su lista y su orden cargan
 * sin errores ni scroll horizontal, las tareas se marcan con un toque y
 * taller ve el listado y la orden por revisar.
 */
beforeEach(function (): void {
    $this->mecanico = actorConRol('mecanico');
    $this->unidad = Vehiculo::factory()->create(['placa' => 'MEC-101', 'marca' => 'SCANIA']);

    $this->orden = OrdenTrabajo::factory()->create([
        'vehiculo_id' => $this->unidad->id,
        'estado' => EstadoOrdenTrabajo::EnProceso,
        'prioridad' => 'urgente',
        'trabajo' => 'Cambio de zapatas del eje 2',
    ]);
    $this->orden->mecanicos()->attach($this->mecanico->id, ['responsable' => true]);
    $this->tarea = TareaOrden::factory()->create(['orden_trabajo_id' => $this->orden->id, 'descripcion' => 'Desmontar ruedas']);
});

it('muestra al mecánico sus órdenes y deja marcar una tarea en celular', function (): void {
    actingAs($this->mecanico);

    visit('/dashboard')
        ->on()->mobile()
        ->assertSee('Tienes 1 trabajo por hacer')
        ->assertSee('MEC101')
        ->assertSee('Urgente')
        ->assertDontSee('Mantenimiento')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'movil-mecanico-lista');

    visit("/mis-ordenes/{$this->orden->id}")
        ->on()->mobile()
        ->assertSee('Cambio de zapatas del eje 2')
        ->assertSee('Terminar trabajo')
        ->assertSee('Tomar foto')
        ->click('Desmontar ruedas')
        ->assertSee('1 de 1')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'movil-mecanico-orden');

    expect($this->tarea->refresh()->hecha_por)->toBe($this->mecanico->id);
});

it('muestra a taller el listado y la orden por revisar en celular', function (): void {
    $this->orden->update(['estado' => EstadoOrdenTrabajo::PorRevisar, 'termino' => now(), 'terminado_por' => $this->mecanico->id]);

    actingAs(actorConRol('taller'));

    visit('/mantenimiento/ordenes')
        ->on()->mobile()
        ->assertSee('Por revisar')
        ->assertSee('MEC101')
        ->assertSee($this->mecanico->name)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'movil-ordenes-lista');

    visit("/mantenimiento/ordenes/{$this->orden->id}/editar")
        ->on()->mobile()
        ->assertSee('El mecánico la terminó')
        ->assertSee('Observar')
        ->assertSee('Terminó el trabajo')
        ->click('2. Trabajo')
        ->assertSee('Mecánicos asignados')
        ->assertSee('Agregar tarea')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'movil-orden-revision');
});
