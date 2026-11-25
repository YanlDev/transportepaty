<?php

use App\Enums\EstadoOrdenTrabajo;
use App\Enums\EstadoVuelta;
use App\Enums\Permiso;
use App\Enums\PrioridadOrden;
use App\Enums\RolBase;
use App\Enums\TipoVehiculo;
use App\Models\Articulo;
use App\Models\OrdenTrabajo;
use App\Models\RepuestoOrden;
use App\Models\TareaOrden;
use App\Models\User;
use App\Models\Vehiculo;
use App\Models\Vuelta;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

/*
 * Las órdenes de trabajo con mecánicos: operaciones crea la pre-orden y
 * elige al mecánico; el mecánico arma el requerimiento (tareas, repuestos,
 * fotos del antes) y lo envía; operaciones lo aprueba y nace la orden; el
 * mecánico la ejecuta con evidencia y operaciones la valida.
 */
beforeEach(function (): void {
    Storage::fake('public');

    $this->taller = actorConRol('taller');
    $this->mecanico = actorConRol('mecanico');
    $this->tracto = Vehiculo::factory()->create(['tipo' => TipoVehiculo::Tracto, 'placa' => 'MEC-101']);
});

function foto(string $nombre = 'foto.jpg'): UploadedFile
{
    return UploadedFile::fake()->image($nombre, 800, 600);
}

/** Una orden aceptada por taller y asignada a esos mecánicos. */
function ordenAsignada(Vehiculo $vehiculo, User ...$mecanicos): OrdenTrabajo
{
    $orden = OrdenTrabajo::factory()->create([
        'vehiculo_id' => $vehiculo->id,
        'estado' => EstadoOrdenTrabajo::Pendiente,
    ]);

    foreach ($mecanicos as $indice => $mecanico) {
        $orden->mecanicos()->attach($mecanico->id, ['responsable' => $indice === 0]);
    }

    return $orden;
}

/** Lo mínimo que manda el formulario de taller. */
function datosDeOrden(Vehiculo $vehiculo, array $extra = []): array
{
    return [
        'vehiculo_id' => $vehiculo->id,
        'tipo' => 'correctiva',
        'estado' => 'pendiente',
        'fecha' => now()->toDateString(),
        'trabajo' => 'Cambio de zapatas del eje 2',
        ...$extra,
    ];
}

it('gives the mechanic role only the permission to execute its orders', function (): void {
    expect(RolBase::Mecanico->permisos())->toBe([Permiso::MantenimientoEjecutar])
        ->and($this->mecanico->can(Permiso::MantenimientoEjecutar->value))->toBeTrue()
        ->and($this->mecanico->can(Permiso::MantenimientoVer->value))->toBeFalse()
        ->and($this->taller->can(Permiso::MantenimientoSolicitar->value))->toBeTrue();
});

it('sends the mechanic to its orders instead of the dashboard', function (): void {
    actingAs($this->mecanico)->get(route('dashboard'))->assertRedirect(route('mis-ordenes.index'));
});

it('keeps the mechanic out of everything else', function (string $ruta): void {
    actingAs($this->mecanico)->get(route($ruta))->assertForbidden();
})->with(['mantenimiento.index', 'mantenimiento.ordenes.index', 'taller.index', 'almacen.index', 'vueltas.index']);

it('registers an order for a trailer', function (): void {
    $carreta = Vehiculo::factory()->create(['tipo' => TipoVehiculo::Carreta, 'placa' => 'CAR-777']);

    actingAs($this->taller)
        ->post(route('mantenimiento.ordenes.store'), datosDeOrden($carreta))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('mantenimiento.show', $carreta));

    expect(OrdenTrabajo::query()->where('vehiculo_id', $carreta->id)->sole()->estado)->toBe(EstadoOrdenTrabajo::Pendiente);

    actingAs($this->taller)->get(route('mantenimiento.show', $carreta))->assertSuccessful();

    actingAs($this->taller)
        ->get(route('mantenimiento.ordenes.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tractos', fn ($unidades) => collect($unidades)->contains('id', $carreta->id)));
});

it('assigns one or several mechanics with a responsible and a task list', function (): void {
    $otro = actorConRol('mecanico');

    actingAs($this->taller)
        ->post(route('mantenimiento.ordenes.store'), datosDeOrden($this->tracto, [
            'prioridad' => 'urgente',
            'mecanicos' => [$this->mecanico->id, $otro->id],
            'responsable_id' => $otro->id,
            'tareas' => [['descripcion' => 'Desmontar ruedas'], ['descripcion' => 'Cambiar zapatas']],
        ]))
        ->assertSessionHasNoErrors();

    $orden = OrdenTrabajo::query()->sole();

    expect($orden->prioridad)->toBe(PrioridadOrden::Urgente)
        ->and($orden->mecanicos()->pluck('users.id')->all())->toEqualCanonicalizing([$this->mecanico->id, $otro->id])
        ->and($orden->mecanicos()->wherePivot('responsable', true)->sole()->id)->toBe($otro->id)
        ->and($orden->tareas->pluck('descripcion')->all())->toBe(['Desmontar ruedas', 'Cambiar zapatas']);
});

it('does not assign someone who is not a mechanic', function (): void {
    actingAs($this->taller)
        ->post(route('mantenimiento.ordenes.store'), datosDeOrden($this->tracto, ['mecanicos' => [actorConRol('rrhh')->id]]))
        ->assertSessionHasErrors('mecanicos.0');
});

it('does not let the workshop pick a state that comes from an action', function (): void {
    actingAs($this->taller)
        ->post(route('mantenimiento.ordenes.store'), datosDeOrden($this->tracto, ['estado' => 'por_revisar']))
        ->assertSessionHasErrors('estado');
});

it('shows the mechanic only the orders assigned to it', function (): void {
    $mia = ordenAsignada($this->tracto, $this->mecanico);
    $ajena = ordenAsignada($this->tracto, actorConRol('mecanico'));

    actingAs($this->mecanico)
        ->get(route('mis-ordenes.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('mecanico/index')
            ->has('por_hacer', 1)
            ->where('por_hacer.0.id', $mia->id)
            ->where('por_hacer.0.placa', 'MEC-101'));

    actingAs($this->mecanico)->get(route('mis-ordenes.show', $mia))->assertSuccessful();
    actingAs($this->mecanico)->get(route('mis-ordenes.show', $ajena))->assertForbidden();
    actingAs($this->mecanico)->post(route('mis-ordenes.iniciar', $ajena), ['fotos' => [foto()]])->assertForbidden();
});

it('does not start without a photo of how the unit arrived', function (): void {
    $orden = ordenAsignada($this->tracto, $this->mecanico);

    actingAs($this->mecanico)
        ->post(route('mis-ordenes.iniciar', $orden))
        ->assertSessionHasErrors('fotos');

    expect($orden->refresh()->estado)->toBe(EstadoOrdenTrabajo::Pendiente);
});

it('starts with the before photo and records who and when', function (): void {
    $orden = ordenAsignada($this->tracto, $this->mecanico);

    actingAs($this->mecanico)
        ->post(route('mis-ordenes.iniciar', $orden), ['fotos' => [foto()], 'kilometraje' => 150200])
        ->assertSessionHasNoErrors();

    $orden->refresh();

    expect($orden->estado)->toBe(EstadoOrdenTrabajo::EnProceso)
        ->and($orden->iniciado_por)->toBe($this->mecanico->id)
        ->and($orden->inicio)->not->toBeNull()
        ->and($orden->kilometraje)->toBe(150200)
        ->and($orden->getMedia('antes'))->toHaveCount(1);
});

it('marks tasks only while the order is being worked on', function (): void {
    $orden = ordenAsignada($this->tracto, $this->mecanico);
    $tarea = TareaOrden::factory()->create(['orden_trabajo_id' => $orden->id]);

    actingAs($this->mecanico)
        ->post(route('mis-ordenes.tareas.marcar', [$orden, $tarea]), ['hecha' => true])
        ->assertSessionHasErrors('tarea');

    $orden->update(['estado' => EstadoOrdenTrabajo::EnProceso]);

    actingAs($this->mecanico)
        ->post(route('mis-ordenes.tareas.marcar', [$orden, $tarea]), ['hecha' => true])
        ->assertSessionHasNoErrors();

    expect($tarea->refresh()->hecha_por)->toBe($this->mecanico->id)
        ->and($tarea->estaHecha())->toBeTrue();
});

it('does not finish without the after photo or with unexplained tasks', function (): void {
    $orden = ordenAsignada($this->tracto, $this->mecanico);
    $orden->update(['estado' => EstadoOrdenTrabajo::EnProceso]);
    TareaOrden::factory()->create(['orden_trabajo_id' => $orden->id]);

    actingAs($this->mecanico)
        ->post(route('mis-ordenes.terminar', $orden))
        ->assertSessionHasErrors('fotos');

    actingAs($this->mecanico)
        ->post(route('mis-ordenes.terminar', $orden), ['fotos' => [foto()]])
        ->assertSessionHasErrors('nota');

    expect($orden->refresh()->estado)->toBe(EstadoOrdenTrabajo::EnProceso)
        ->and($orden->getMedia('despues'))->toHaveCount(0);
});

it('finishes with the after photo and hands the order to the workshop', function (): void {
    $orden = ordenAsignada($this->tracto, $this->mecanico);
    $orden->update(['estado' => EstadoOrdenTrabajo::EnProceso]);

    actingAs($this->mecanico)
        ->post(route('mis-ordenes.terminar', $orden), ['fotos' => [foto()], 'nota' => 'Quedó regulado.'])
        ->assertRedirect(route('mis-ordenes.index'));

    $orden->refresh();

    expect($orden->estado)->toBe(EstadoOrdenTrabajo::PorRevisar)
        ->and($orden->terminado_por)->toBe($this->mecanico->id)
        ->and($orden->termino)->not->toBeNull()
        ->and($orden->nota_mecanico)->toBe('Quedó regulado.');

    // Ya está en manos de taller: el mecánico no le cambia las fotos.
    actingAs($this->mecanico)
        ->post(route('mis-ordenes.fotos.store', $orden), ['coleccion' => 'despues', 'fotos' => [foto()]])
        ->assertSessionHasErrors('fotos');
});

it('lets the workshop send the order back with what to fix', function (): void {
    $orden = ordenAsignada($this->tracto, $this->mecanico);
    $orden->update(['estado' => EstadoOrdenTrabajo::PorRevisar]);

    actingAs($this->mecanico)
        ->post(route('mantenimiento.ordenes.observar', $orden), ['motivo' => 'Falta ajustar la rueda 4'])
        ->assertForbidden();

    actingAs($this->taller)
        ->post(route('mantenimiento.ordenes.observar', $orden), ['motivo' => 'Falta ajustar la rueda 4'])
        ->assertSessionHasNoErrors();

    $orden->refresh();

    expect($orden->estado)->toBe(EstadoOrdenTrabajo::Observada)
        ->and($orden->revisado_por)->toBe($this->taller->id)
        ->and($orden->observacion_revision)->toBe('Falta ajustar la rueda 4');

    actingAs($this->mecanico)
        ->get(route('mis-ordenes.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('por_hacer.0.observacion_revision', 'Falta ajustar la rueda 4'));

    // El mecánico la corrige y la vuelve a terminar.
    actingAs($this->mecanico)
        ->post(route('mis-ordenes.terminar', $orden), ['fotos' => [foto()]])
        ->assertSessionHasNoErrors();

    expect($orden->refresh()->estado)->toBe(EstadoOrdenTrabajo::PorRevisar);
});

it('records the workshop user who finalizes the order', function (): void {
    $orden = ordenAsignada($this->tracto, $this->mecanico);
    $orden->update(['estado' => EstadoOrdenTrabajo::PorRevisar, 'terminado_por' => $this->mecanico->id]);

    actingAs($this->taller)
        ->put(route('mantenimiento.ordenes.update', $orden), datosDeOrden($this->tracto, ['estado' => 'finalizada']))
        ->assertSessionHasNoErrors();

    $orden->refresh();

    expect($orden->estado)->toBe(EstadoOrdenTrabajo::Finalizada)
        ->and($orden->revisado_por)->toBe($this->taller->id)
        ->and($orden->revisado_at)->not->toBeNull()
        // Editar la orden sin mandar mecánicos no los quita.
        ->and($orden->mecanicos()->count())->toBe(1);

    actingAs($this->mecanico)
        ->get(route('mis-ordenes.index'))
        ->assertInertia(fn (Assert $page) => $page->has('por_hacer', 0)->has('terminadas', 1));
});

it('lets operations create a pre-order for a mechanic, and it blocks the unit', function (): void {
    $operaciones = actorConRol('operaciones');
    $vuelta = Vuelta::factory()->create([
        'tracto_id' => $this->tracto->id,
        'estado' => EstadoVuelta::Programada,
        'inicio' => now()->toDateString(),
    ]);

    // Sin mecánico nadie arma el requerimiento.
    actingAs($operaciones)
        ->post(route('taller.solicitudes.store'), ['vehiculo_id' => $this->tracto->id, 'trabajo' => 'Fuga de aire en el eje 3', 'prioridad' => 'normal'])
        ->assertSessionHasErrors('mecanicos');

    actingAs($operaciones)
        ->post(route('taller.solicitudes.store'), [
            'vehiculo_id' => $this->tracto->id,
            'trabajo' => 'Fuga de aire en la válvula del eje 3',
            'prioridad' => 'urgente',
            'mecanicos' => [$this->mecanico->id],
            'fotos' => [foto()],
        ])
        ->assertSessionHasNoErrors();

    $orden = OrdenTrabajo::query()->sole();

    expect($orden->estado)->toBe(EstadoOrdenTrabajo::Solicitada)
        ->and($orden->user_id)->toBe($operaciones->id)
        ->and($orden->esDeMecanico($this->mecanico))->toBeTrue()
        ->and($orden->getMedia('antes'))->toHaveCount(1);

    actingAs($operaciones)
        ->get(route('taller.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('puede.solicitar_trabajo', true)
            ->has('mecanicos', 1)
            ->where('ordenes.0.estado_valor', 'solicitada'));

    // Desde la pre-orden la unidad está en taller: no recibe el apto.
    actingAs($this->taller)
        ->post(route('taller.store', $vuelta), ['resultado' => 'apto'])
        ->assertSessionHasErrors('resultado');

    actingAs($this->mecanico)
        ->post(route('taller.solicitudes.store'), ['vehiculo_id' => $this->tracto->id, 'trabajo' => 'Algo que revisar', 'prioridad' => 'normal', 'mecanicos' => [$this->mecanico->id]])
        ->assertForbidden();
});

/** Una pre-orden de operaciones para ese mecánico. */
function preOrden(Vehiculo $vehiculo, User $mecanico): OrdenTrabajo
{
    $orden = OrdenTrabajo::factory()->create([
        'vehiculo_id' => $vehiculo->id,
        'estado' => EstadoOrdenTrabajo::Solicitada,
        'tecnico' => null,
    ]);
    $orden->mecanicos()->attach($mecanico->id, ['responsable' => true]);

    return $orden;
}

it('lets the mechanic build the request: tasks, parts and before photos', function (): void {
    $orden = preOrden($this->tracto, $this->mecanico);
    $articulo = Articulo::factory()->create(['nombre' => 'Válvula relé', 'activo' => true]);

    actingAs($this->mecanico)
        ->get(route('mis-ordenes.index'))
        ->assertInertia(fn (Assert $page) => $page->where('por_hacer.0.id', $orden->id)->where('por_hacer.0.estado', 'solicitada'));

    actingAs($this->mecanico)
        ->post(route('mis-ordenes.tareas.store', $orden), ['descripcion' => 'Cambiar la válvula relé'])
        ->assertSessionHasNoErrors();

    // Del catálogo: toma el nombre y la unidad del artículo.
    actingAs($this->mecanico)
        ->post(route('mis-ordenes.repuestos.store', $orden), ['articulo_id' => $articulo->id, 'cantidad' => 1])
        ->assertSessionHasNoErrors();

    // En texto, si no está en el almacén.
    actingAs($this->mecanico)
        ->post(route('mis-ordenes.repuestos.store', $orden), ['descripcion' => 'Manguera de aire 1/2', 'cantidad' => 2.5, 'unidad' => 'm'])
        ->assertSessionHasNoErrors();

    actingAs($this->mecanico)
        ->post(route('mis-ordenes.repuestos.store', $orden), ['cantidad' => 1])
        ->assertSessionHasErrors('descripcion');

    $repuestos = $orden->repuestos()->get();

    expect($repuestos)->toHaveCount(2)
        ->and($repuestos[0]->descripcion)->toBe('Válvula relé')
        ->and($repuestos[0]->articulo_id)->toBe($articulo->id)
        ->and($repuestos[0]->adicional)->toBeFalse()
        ->and($repuestos[1]->articulo_id)->toBeNull()
        ->and($repuestos[1]->unidad)->toBe('m');

    // Se arrepiente de uno antes de enviar.
    actingAs($this->mecanico)
        ->delete(route('mis-ordenes.repuestos.destroy', [$orden, $repuestos[1]]))
        ->assertSessionHasNoErrors();

    actingAs($this->mecanico)
        ->get(route('mis-ordenes.show', $orden))
        ->assertInertia(fn (Assert $page) => $page
            ->has('orden.repuestos', 1)
            ->where('orden.repuestos.0.del_catalogo', true)
            ->has('articulos', 1)
            ->missing('articulos.0.costo_promedio'));
});

it('does not send the request without a task and a before photo', function (): void {
    $orden = preOrden($this->tracto, $this->mecanico);

    actingAs($this->mecanico)->post(route('mis-ordenes.enviar', $orden))->assertSessionHasErrors('tareas');

    TareaOrden::factory()->create(['orden_trabajo_id' => $orden->id]);

    actingAs($this->mecanico)->post(route('mis-ordenes.enviar', $orden))->assertSessionHasErrors('fotos');

    actingAs($this->mecanico)
        ->post(route('mis-ordenes.fotos.store', $orden), ['coleccion' => 'antes', 'fotos' => [foto()]])
        ->assertSessionHasNoErrors();

    actingAs($this->mecanico)
        ->post(route('mis-ordenes.enviar', $orden))
        ->assertRedirect(route('mis-ordenes.index'));

    $orden->refresh();

    expect($orden->estado)->toBe(EstadoOrdenTrabajo::Requerimiento)
        ->and($orden->requerimiento_por)->toBe($this->mecanico->id);

    // Enviado, ya no lo cambia: espera a operaciones.
    actingAs($this->mecanico)
        ->post(route('mis-ordenes.tareas.store', $orden), ['descripcion' => 'Otra tarea más'])
        ->assertSessionHasErrors('tarea');
    actingAs($this->mecanico)
        ->post(route('mis-ordenes.iniciar', $orden), ['fotos' => [foto()]])
        ->assertSessionHasErrors('estado');
});

it('lets operations approve the request and the order is born', function (): void {
    $operaciones = actorConRol('operaciones');
    $orden = preOrden($this->tracto, $this->mecanico);
    $orden->update(['estado' => EstadoOrdenTrabajo::Requerimiento, 'requerimiento_at' => now(), 'requerimiento_por' => $this->mecanico->id]);
    $repuesto = RepuestoOrden::factory()->create(['orden_trabajo_id' => $orden->id]);
    $orden->addMedia(foto())->toMediaCollection('antes');

    actingAs($this->mecanico)
        ->post(route('mantenimiento.ordenes.requerimiento.aprobar', $orden))
        ->assertForbidden();

    actingAs($operaciones)
        ->get(route('mantenimiento.ordenes.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('por_atender.requerimientos', 1)
            ->where('ordenes.data.0.id', $orden->id));

    actingAs($operaciones)
        ->get(route('mantenimiento.ordenes.edit', $orden))
        ->assertInertia(fn (Assert $page) => $page
            ->where('orden.puede_aprobar_requerimiento', true)
            ->has('ejecucion.repuestos', 1));

    actingAs($operaciones)
        ->post(route('mantenimiento.ordenes.requerimiento.aprobar', $orden))
        ->assertSessionHasNoErrors();

    $orden->refresh();

    expect($orden->estado)->toBe(EstadoOrdenTrabajo::Pendiente)
        ->and($orden->aprobado_por)->toBe($operaciones->id)
        ->and($repuesto->refresh()->estaAprobado())->toBeTrue();

    // Con las fotos del requerimiento, el mecánico empieza sin subir otra.
    actingAs($this->mecanico)
        ->post(route('mis-ordenes.iniciar', $orden))
        ->assertSessionHasNoErrors();

    expect($orden->refresh()->estado)->toBe(EstadoOrdenTrabajo::EnProceso);
});

it('lets operations send the request back with what to change', function (): void {
    $operaciones = actorConRol('operaciones');
    $orden = preOrden($this->tracto, $this->mecanico);
    $orden->update(['estado' => EstadoOrdenTrabajo::Requerimiento]);

    actingAs($operaciones)
        ->post(route('mantenimiento.ordenes.requerimiento.devolver', $orden), ['motivo' => 'Falta la manguera en los repuestos'])
        ->assertSessionHasNoErrors();

    expect($orden->refresh()->estado)->toBe(EstadoOrdenTrabajo::Solicitada);

    actingAs($this->mecanico)
        ->get(route('mis-ordenes.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('por_hacer.0.devolucion_requerimiento', 'Falta la manguera en los repuestos'));
});

it('treats a part asked during the job as an extra that operations approves', function (): void {
    $operaciones = actorConRol('operaciones');
    $orden = ordenAsignada($this->tracto, $this->mecanico);
    $orden->update(['estado' => EstadoOrdenTrabajo::EnProceso]);

    actingAs($this->mecanico)
        ->post(route('mis-ordenes.repuestos.store', $orden), ['descripcion' => 'Abrazadera de 1/2', 'cantidad' => 2])
        ->assertSessionHasNoErrors();

    $repuesto = $orden->repuestos()->sole();

    expect($repuesto->adicional)->toBeTrue()
        ->and($repuesto->estaAprobado())->toBeFalse();

    actingAs($operaciones)
        ->get(route('mantenimiento.ordenes.index'))
        ->assertInertia(fn (Assert $page) => $page->where('por_atender.adicionales', 1));

    actingAs($operaciones)
        ->post(route('mantenimiento.ordenes.repuestos.aprobar', [$orden, $repuesto]))
        ->assertSessionHasNoErrors();

    expect($repuesto->refresh()->aprobado_por)->toBe($operaciones->id);

    // Aprobado, el mecánico ya no lo quita.
    actingAs($this->mecanico)
        ->delete(route('mis-ordenes.repuestos.destroy', [$orden, $repuesto]))
        ->assertSessionHasErrors('repuesto');
});

it('lets operations validate the finished job', function (): void {
    $operaciones = actorConRol('operaciones');
    $orden = ordenAsignada($this->tracto, $this->mecanico);
    $orden->update(['estado' => EstadoOrdenTrabajo::PorRevisar]);

    actingAs($operaciones)
        ->put(route('mantenimiento.ordenes.update', $orden), datosDeOrden($this->tracto, ['estado' => 'finalizada']))
        ->assertSessionHasNoErrors();

    expect($orden->refresh()->estado)->toBe(EstadoOrdenTrabajo::Finalizada)
        ->and($orden->revisado_por)->toBe($operaciones->id);
});

it('rejects a pre-order that will not be attended', function (): void {
    $orden = preOrden($this->tracto, $this->mecanico);

    actingAs($this->taller)
        ->post(route('mantenimiento.ordenes.rechazar', $orden), ['motivo' => 'Ya se atendió ayer'])
        ->assertSessionHasNoErrors();

    expect($orden->refresh()->estaAnulado())->toBeTrue()
        ->and($orden->motivo_anulacion)->toBe('Ya se atendió ayer');
});

it('does not hand out parts from the warehouse catalog to a pre-order', function (): void {
    $orden = preOrden($this->tracto, $this->mecanico);

    actingAs($this->taller)
        ->get(route('almacen.salidas.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('ordenes', fn ($ordenes) => ! collect($ordenes)->contains('id', $orden->id)));
});

it('opens a pre-order when the unit is not fit to leave', function (): void {
    $vuelta = Vuelta::factory()->create([
        'tracto_id' => $this->tracto->id,
        'estado' => EstadoVuelta::Programada,
        'inicio' => now()->toDateString(),
    ]);

    actingAs($this->taller)
        ->post(route('taller.store', $vuelta), [
            'resultado' => 'no_apto',
            'disponible_estimado' => now()->addDay()->format('Y-m-d H:i'),
            'motivo' => 'Fuga de aceite en el cárter',
            'crear_orden' => true,
        ])
        ->assertSessionHasNoErrors();

    $orden = OrdenTrabajo::query()->sole();

    expect($orden->vehiculo_id)->toBe($this->tracto->id)
        ->and($orden->estado)->toBe(EstadoOrdenTrabajo::Solicitada)
        ->and($orden->prioridad)->toBe(PrioridadOrden::Urgente)
        ->and($orden->trabajo)->toBe('Fuga de aceite en el cárter');
});

it('lists every order for the workshop with what is waiting for review', function (): void {
    $porRevisar = ordenAsignada($this->tracto, $this->mecanico);
    $porRevisar->update(['estado' => EstadoOrdenTrabajo::PorRevisar]);
    OrdenTrabajo::factory()->create(['estado' => EstadoOrdenTrabajo::Finalizada]);

    actingAs($this->taller)
        ->get(route('mantenimiento.ordenes.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('mantenimiento/ordenes')
            ->has('ordenes.data', 1)
            ->where('ordenes.data.0.id', $porRevisar->id)
            ->where('ordenes.data.0.mecanicos', [$this->mecanico->name])
            ->where('por_atender.por_revisar', 1));

    actingAs($this->taller)
        ->get(route('mantenimiento.ordenes.index', ['estado' => 'todas', 'mecanico' => $this->mecanico->id]))
        ->assertInertia(fn (Assert $page) => $page->has('ordenes.data', 1));
});

/*
 * Cuando el mecánico no puede anotar desde su celular, quien gestiona la
 * orden anota por él: mismos pasos y mismas reglas, a nombre del mecánico y
 * con quién lo anotó.
 */
it('lets the workshop register every step on behalf of the assigned mechanic', function (): void {
    $orden = ordenAsignada($this->tracto, $this->mecanico);
    $orden->update(['estado' => EstadoOrdenTrabajo::Solicitada]);
    $por = ['por' => $this->mecanico->id];

    actingAs($this->taller)
        ->get(route('mantenimiento.ordenes.edit', $orden))
        ->assertInertia(fn (Assert $page) => $page->where('orden.puede_registrar_por_mecanico', true));

    actingAs($this->taller)
        ->get(route('mis-ordenes.show', [$orden, ...$por]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('mecanico/orden')
            ->where('por.id', $this->mecanico->id)
            ->where('por.nombre', $this->mecanico->name));

    // El requerimiento: tarea, repuesto, foto del antes y envío.
    actingAs($this->taller)
        ->post(route('mis-ordenes.tareas.store', [$orden, ...$por]), ['descripcion' => 'Cambiar llanta de la posición 3'])
        ->assertSessionHasNoErrors();
    actingAs($this->taller)
        ->post(route('mis-ordenes.repuestos.store', [$orden, ...$por]), ['descripcion' => 'Válvula', 'cantidad' => 1, 'unidad' => 'und'])
        ->assertSessionHasNoErrors();
    actingAs($this->taller)
        ->post(route('mis-ordenes.fotos.store', [$orden, ...$por]), ['coleccion' => 'antes', 'fotos' => [foto()]])
        ->assertSessionHasNoErrors();
    actingAs($this->taller)
        ->post(route('mis-ordenes.enviar', [$orden, ...$por]))
        ->assertRedirect(route('mantenimiento.ordenes.edit', $orden));

    $orden->refresh();
    $repuesto = $orden->repuestos()->sole();

    expect($orden->estado)->toBe(EstadoOrdenTrabajo::Requerimiento)
        ->and($orden->requerimiento_por)->toBe($this->mecanico->id)
        ->and($orden->requerimiento_registrado_por)->toBe($this->taller->id)
        ->and($repuesto->user_id)->toBe($this->mecanico->id)
        ->and($repuesto->registrado_por)->toBe($this->taller->id);

    actingAs($this->taller)
        ->post(route('mantenimiento.ordenes.requerimiento.aprobar', $orden))
        ->assertSessionHasNoErrors();

    // La ejecución: empezar, marcar la tarea y terminar con la foto del después.
    actingAs($this->taller)
        ->post(route('mis-ordenes.iniciar', [$orden, ...$por]))
        ->assertSessionHasNoErrors();

    $tarea = $orden->tareas()->sole();

    actingAs($this->taller)
        ->post(route('mis-ordenes.tareas.marcar', [$orden, $tarea, ...$por]), ['hecha' => true])
        ->assertSessionHasNoErrors();
    actingAs($this->taller)
        ->post(route('mis-ordenes.terminar', [$orden, ...$por]), ['fotos' => [foto()]])
        ->assertRedirect(route('mantenimiento.ordenes.edit', $orden));

    $orden->refresh();
    $tarea->refresh();

    expect($orden->estado)->toBe(EstadoOrdenTrabajo::PorRevisar)
        ->and($orden->iniciado_por)->toBe($this->mecanico->id)
        ->and($orden->inicio_registrado_por)->toBe($this->taller->id)
        ->and($orden->terminado_por)->toBe($this->mecanico->id)
        ->and($orden->termino_registrado_por)->toBe($this->taller->id)
        ->and($tarea->hecha_por)->toBe($this->mecanico->id)
        ->and($tarea->registrada_por)->toBe($this->taller->id);

    // El historial dice quién hizo el trabajo y quién lo anotó.
    actingAs($this->taller)
        ->get(route('mantenimiento.ordenes.edit', $orden))
        ->assertInertia(fn (Assert $page) => $page
            ->where('orden.puede_registrar_por_mecanico', false)
            ->where('ejecucion.tareas.0.registrada_por', $this->taller->name)
            ->where('ejecucion.pasos', fn ($pasos) => collect($pasos)
                ->where('paso', 'Terminó el trabajo')
                ->where('usuario', $this->mecanico->name)
                ->where('registrado_por', $this->taller->name)
                ->isNotEmpty()));

    // Quien anotó también puede validar: queda a la vista en el historial.
    actingAs($this->taller)
        ->put(route('mantenimiento.ordenes.update', $orden), datosDeOrden($this->tracto, ['estado' => 'finalizada']))
        ->assertSessionHasNoErrors();

    expect($orden->refresh()->estado)->toBe(EstadoOrdenTrabajo::Finalizada)
        ->and($orden->revisado_por)->toBe($this->taller->id);
});

it('asks for the same evidence when someone registers for the mechanic', function (): void {
    $orden = ordenAsignada($this->tracto, $this->mecanico);
    $por = ['por' => $this->mecanico->id];

    actingAs($this->taller)
        ->post(route('mis-ordenes.iniciar', [$orden, ...$por]))
        ->assertSessionHasErrors('fotos');

    $orden->update(['estado' => EstadoOrdenTrabajo::EnProceso]);

    actingAs($this->taller)
        ->post(route('mis-ordenes.terminar', [$orden, ...$por]))
        ->assertSessionHasErrors('fotos');

    expect($orden->refresh()->estado)->toBe(EstadoOrdenTrabajo::EnProceso);
});

it('registers only for a mechanic assigned to the order, and only who manages it', function (): void {
    $orden = ordenAsignada($this->tracto, $this->mecanico);
    $otro = actorConRol('mecanico');

    // A nombre de alguien que no tiene la orden.
    actingAs($this->taller)
        ->post(route('mis-ordenes.iniciar', [$orden, 'por' => $otro->id]), ['fotos' => [foto()]])
        ->assertForbidden();

    // Un mecánico no anota por otro, aunque el otro sí tenga la orden.
    actingAs($otro)
        ->post(route('mis-ordenes.iniciar', [$orden, 'por' => $this->mecanico->id]), ['fotos' => [foto()]])
        ->assertForbidden();

    // Y taller, sin decir por quién, no entra a la pantalla del mecánico.
    actingAs($this->taller)->get(route('mis-ordenes.show', $orden))->assertForbidden();

    expect($orden->refresh()->estado)->toBe(EstadoOrdenTrabajo::Pendiente);
});

it('leaves no registrar when the mechanic works from its own user', function (): void {
    $orden = ordenAsignada($this->tracto, $this->mecanico);

    actingAs($this->mecanico)
        ->post(route('mis-ordenes.iniciar', $orden), ['fotos' => [foto()]])
        ->assertSessionHasNoErrors();

    expect($orden->refresh()->iniciado_por)->toBe($this->mecanico->id)
        ->and($orden->inicio_registrado_por)->toBeNull();

    actingAs($this->mecanico)
        ->get(route('mis-ordenes.show', $orden))
        ->assertInertia(fn (Assert $page) => $page->where('por', null));
});
