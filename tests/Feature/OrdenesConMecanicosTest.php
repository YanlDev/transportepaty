<?php

use App\Enums\EstadoOrdenTrabajo;
use App\Enums\EstadoVuelta;
use App\Enums\Permiso;
use App\Enums\PrioridadOrden;
use App\Enums\RolBase;
use App\Enums\TipoVehiculo;
use App\Models\OrdenTrabajo;
use App\Models\TareaOrden;
use App\Models\User;
use App\Models\Vehiculo;
use App\Models\Vuelta;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

/*
 * Las órdenes de trabajo con mecánicos: taller asigna, el mecánico ve solo
 * las suyas y las ejecuta con evidencia (foto del antes para empezar, foto
 * del después para terminar), y taller revisa antes de finalizar.
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

it('lets operations ask the workshop for a job without blocking the unit', function (): void {
    $operaciones = actorConRol('operaciones');
    $vuelta = Vuelta::factory()->create([
        'tracto_id' => $this->tracto->id,
        'estado' => EstadoVuelta::Programada,
        'inicio' => now()->toDateString(),
    ]);

    actingAs($operaciones)
        ->post(route('taller.solicitudes.store'), [
            'vehiculo_id' => $this->tracto->id,
            'trabajo' => 'Fuga de aire en la válvula del eje 3',
            'prioridad' => 'urgente',
            'fotos' => [foto()],
        ])
        ->assertSessionHasNoErrors();

    $orden = OrdenTrabajo::query()->sole();

    expect($orden->estado)->toBe(EstadoOrdenTrabajo::Solicitada)
        ->and($orden->user_id)->toBe($operaciones->id)
        ->and($orden->getMedia('antes'))->toHaveCount(1);

    actingAs($operaciones)
        ->get(route('taller.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('puede.solicitar_trabajo', true)
            ->where('ordenes.0.estado_valor', 'solicitada')
            ->where('ordenes.0.solicitante', $operaciones->name));

    // Una solicitud todavía no es trabajo de taller: no frena el apto.
    actingAs($this->taller)
        ->post(route('taller.store', $vuelta), ['resultado' => 'apto'])
        ->assertSessionHasNoErrors();

    actingAs($this->mecanico)
        ->post(route('taller.solicitudes.store'), ['vehiculo_id' => $this->tracto->id, 'trabajo' => 'Algo que revisar', 'prioridad' => 'normal'])
        ->assertForbidden();
});

it('lets the workshop accept or reject a request', function (): void {
    $aceptada = OrdenTrabajo::factory()->create(['vehiculo_id' => $this->tracto->id, 'estado' => EstadoOrdenTrabajo::Solicitada]);
    $rechazada = OrdenTrabajo::factory()->create(['vehiculo_id' => $this->tracto->id, 'estado' => EstadoOrdenTrabajo::Solicitada]);

    actingAs($this->taller)
        ->put(route('mantenimiento.ordenes.update', $aceptada), datosDeOrden($this->tracto, ['mecanicos' => [$this->mecanico->id]]))
        ->assertSessionHasNoErrors();

    actingAs($this->taller)
        ->post(route('mantenimiento.ordenes.rechazar', $rechazada), ['motivo' => 'Ya se atendió ayer'])
        ->assertSessionHasNoErrors();

    expect($aceptada->refresh()->estado)->toBe(EstadoOrdenTrabajo::Pendiente)
        ->and($aceptada->esDeMecanico($this->mecanico))->toBeTrue()
        ->and($rechazada->refresh()->estaAnulado())->toBeTrue()
        ->and($rechazada->motivo_anulacion)->toBe('Ya se atendió ayer');
});

it('opens the work order when the unit is not fit to leave', function (): void {
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
        ->and($orden->estado)->toBe(EstadoOrdenTrabajo::Pendiente)
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
