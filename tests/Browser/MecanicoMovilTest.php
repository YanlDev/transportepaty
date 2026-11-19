<?php

use App\Enums\EstadoOrdenTrabajo;
use App\Models\OrdenTrabajo;
use App\Models\TareaOrden;
use App\Models\Vehiculo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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

it('deja al mecánico armar y enviar el requerimiento desde el celular', function (): void {
    Storage::fake('public');

    $orden = OrdenTrabajo::factory()->create([
        'vehiculo_id' => $this->unidad->id,
        'estado' => EstadoOrdenTrabajo::Solicitada,
        'trabajo' => 'Fuga de aire en el eje 3',
    ]);
    $orden->mecanicos()->attach($this->mecanico->id, ['responsable' => true]);
    $orden->addMedia(UploadedFile::fake()->image('antes.jpg', 600, 400))->toMediaCollection('antes');

    actingAs($this->mecanico);

    visit("/mis-ordenes/{$orden->id}")
        ->on()->mobile()
        ->assertSee('Es una pre-orden')
        ->assertSee('Para enviar falta: al menos una tarea')
        ->fill('[aria-label="Nueva tarea"]', 'Cambiar la válvula relé')
        ->click('Agregar')
        ->assertSee('1 tarea')
        ->click('Agregar repuesto')
        ->fill('#repuesto-descripcion', 'Válvula relé')
        ->click('button[type=submit]:has-text("Agregar"):below(#repuesto-descripcion)')
        ->assertSee('Por aprobar')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'movil-mecanico-requerimiento')
        ->click('Enviar a operaciones')
        ->assertSee('Esperando a operaciones');

    expect($orden->refresh()->estado)->toBe(EstadoOrdenTrabajo::Requerimiento)
        ->and($orden->repuestos()->sole()->descripcion)->toBe('Válvula relé');
});
