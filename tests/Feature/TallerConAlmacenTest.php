<?php

use App\Enums\EstadoOrdenTrabajo;
use App\Enums\Permiso;
use App\Enums\TipoOrdenTrabajo;
use App\Models\Articulo;
use App\Models\IngresoAlmacen;
use App\Models\OrdenTrabajo;
use App\Models\PlanMantenimiento;
use App\Models\SalidaAlmacen;
use App\Models\ServicioMantenimiento;
use App\Models\User;
use App\Models\Vehiculo;
use App\Services\Kardex;
use App\Services\MaterialesDeOrden;
use App\Services\ProximoMantenimiento;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

/*
 * Mantenimiento y Almacén juntos: lo que una orden consume sale con vale, y
 * de los vales sale su costo. El reloj queda en noviembre de 2026, el único
 * mes abierto del almacén.
 */
beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-11-09 17:00:00'));
    Storage::fake('public');
    Storage::fake('local');
});

/** Entra stock del artículo por una compra, a ese costo. */
function entraAlAlmacen(Articulo $articulo, float $cantidad, float $costo = 10): void
{
    $ingreso = IngresoAlmacen::factory()->create(['fecha' => '2026-11-02']);
    $ingreso->detalles()->create([
        'articulo_id' => $articulo->id,
        'cantidad' => $cantidad,
        'precio_unitario' => $costo,
        'factor' => 1,
        'cantidad_base' => $cantidad,
        'costo_unitario' => $costo,
        'costo_total' => round($cantidad * $costo, 2),
    ]);

    app(Kardex::class)->registrarIngreso($ingreso);
}

/**
 * Un plan con un solo nivel X que pide un filtro (ligado al almacén), dos
 * litros de aceite (ligado) y una calibración (servicio, no sale del almacén).
 *
 * @return array{0: ServicioMantenimiento, 1: Articulo, 2: Articulo, 3: Vehiculo}
 */
function planConCatalogo(): array
{
    $plan = PlanMantenimiento::factory()->create(['marca' => 'KENWORTH']);
    $servicio = ServicioMantenimiento::factory()->create(['plan_mantenimiento_id' => $plan->id, 'codigo' => 'X', 'nivel' => 1]);
    $filtro = Articulo::factory()->create(['nombre' => 'FILTRO DE ACEITE LF14000']);
    $aceite = Articulo::factory()->create(['nombre' => 'ACEITE 15W40']);

    $servicio->repuestos()->createMany([
        ['descripcion' => 'Filtro de aceite', 'cantidad' => 1, 'unidad' => 'und', 'articulo_id' => $filtro->id, 'orden' => 0],
        ['descripcion' => 'Aceite de motor', 'cantidad' => 2, 'unidad' => 'L', 'articulo_id' => $aceite->id, 'orden' => 1],
        ['descripcion' => 'Calibración de válvulas', 'cantidad' => 1, 'unidad' => 'serv', 'es_servicio' => true, 'orden' => 2],
    ]);

    return [$servicio, $filtro, $aceite, Vehiculo::factory()->create(['marca' => 'KENWORTH'])];
}

function preventivaAbierta(Vehiculo $unidad, ServicioMantenimiento $servicio): OrdenTrabajo
{
    return OrdenTrabajo::factory()->create([
        'vehiculo_id' => $unidad->id,
        'tipo' => TipoOrdenTrabajo::Preventiva,
        'servicio_mantenimiento_id' => $servicio->id,
        'estado' => EstadoOrdenTrabajo::EnProceso,
        'kilometraje' => 50000,
        'fecha' => '2026-11-05',
    ]);
}

/**
 * Emite un vale a la orden, como lo hace el almacén.
 *
 * @param  list<array{0: Articulo, 1: float}>  $lineas
 */
function valeParaOrden(OrdenTrabajo $orden, array $lineas): SalidaAlmacen
{
    actingAs(actorConRol('admin'))
        ->post(route('almacen.salidas.store'), [
            'fecha' => '2026-11-06',
            'entregado_a' => 'Juan Quispe',
            'destino' => 'orden_trabajo',
            'orden_trabajo_id' => $orden->id,
            'detalles' => array_map(fn (array $linea): array => ['articulo_id' => $linea[0]->id, 'cantidad' => $linea[1]], $lineas),
        ])
        ->assertSessionHasNoErrors();

    return SalidaAlmacen::query()->latest('id')->firstOrFail();
}

/**
 * @return array<string, mixed>
 */
function finalizar(OrdenTrabajo $orden, array $extra = []): array
{
    return [
        'tipo' => $orden->tipo->value,
        'servicio_mantenimiento_id' => $orden->servicio_mantenimiento_id,
        'estado' => 'finalizada',
        'fecha' => $orden->fecha->toDateString(),
        'kilometraje' => $orden->kilometraje,
        'trabajo' => $orden->trabajo,
        ...$extra,
    ];
}

/** Taller con lo que tiene el rol base: gestiona órdenes, no anula. */
function mecanico(): User
{
    $rol = Role::findOrCreate('taller', 'web');
    $rol->syncPermissions([Permiso::MantenimientoVer->value, Permiso::MantenimientoGestionar->value, Permiso::AlmacenVer->value]);

    return User::factory()->create()->assignRole($rol);
}

// ─── La regla: lo que consume la orden sale con vale ────────────────────────

it('does not finish a preventiva while its parts have not left the warehouse', function (): void {
    [$servicio, $filtro, $aceite, $unidad] = planConCatalogo();
    entraAlAlmacen($filtro, 5);
    entraAlAlmacen($aceite, 20);
    $orden = preventivaAbierta($unidad, $servicio);
    valeParaOrden($orden, [[$filtro, 1]]);

    actingAs(mecanico())
        ->put(route('mantenimiento.ordenes.update', $orden), finalizar($orden))
        ->assertSessionHasErrors('justificacion_materiales');

    expect($orden->refresh()->estado)->toBe(EstadoOrdenTrabajo::EnProceso)
        ->and(session('errors')->first('justificacion_materiales'))
        ->toContain('Aceite de motor')
        ->not->toContain('Filtro de aceite')
        ->not->toContain('Calibración');
});

it('finishes the preventiva once everything its level asks for was issued', function (): void {
    [$servicio, $filtro, $aceite, $unidad] = planConCatalogo();
    entraAlAlmacen($filtro, 5);
    entraAlAlmacen($aceite, 20);
    $orden = preventivaAbierta($unidad, $servicio);
    valeParaOrden($orden, [[$filtro, 1], [$aceite, 2]]);

    actingAs(mecanico())
        ->put(route('mantenimiento.ordenes.update', $orden), finalizar($orden))
        ->assertSessionHasNoErrors();

    expect($orden->refresh()->estado)->toBe(EstadoOrdenTrabajo::Finalizada);
});

it('counts what was returned to the warehouse as not issued', function (): void {
    [$servicio, $filtro, $aceite, $unidad] = planConCatalogo();
    entraAlAlmacen($filtro, 5);
    entraAlAlmacen($aceite, 20);
    $orden = preventivaAbierta($unidad, $servicio);
    $vale = valeParaOrden($orden, [[$filtro, 1], [$aceite, 2]]);

    actingAs(actorConRol('admin'))
        ->post(route('almacen.salidas.devolver', $vale), [
            'fecha' => '2026-11-07',
            'detalles' => [['detalle_salida_id' => $vale->detalles()->where('articulo_id', $aceite->id)->value('id'), 'cantidad' => 1.5]],
        ])
        ->assertSessionHasNoErrors();

    $materiales = app(MaterialesDeOrden::class);

    expect($materiales->despachado($orden)[$aceite->id])->toBe(0.5)
        ->and($materiales->faltantes($servicio, $orden))->toBe(['Aceite de motor'])
        ->and($materiales->costo($orden))->toBe(15.0);
});

it('lets a preventiva close without a part when it says why, and keeps the reason', function (): void {
    [$servicio, , , $unidad] = planConCatalogo();
    $orden = preventivaAbierta($unidad, $servicio);

    actingAs(mecanico())
        ->put(route('mantenimiento.ordenes.update', $orden), finalizar($orden, ['justificacion_materiales' => 'corto']))
        ->assertSessionHasErrors('justificacion_materiales');

    actingAs(mecanico())
        ->put(route('mantenimiento.ordenes.update', $orden), finalizar($orden, [
            'justificacion_materiales' => 'El filtro y el aceite se cambiaron hace 2 mil km en ruta.',
        ]))
        ->assertSessionHasNoErrors();

    expect($orden->refresh()->estado)->toBe(EstadoOrdenTrabajo::Finalizada)
        ->and($orden->justificacion_materiales)->toContain('en ruta');
});

it('treats a plan item without a warehouse article as not issued', function (): void {
    [$servicio, , , $unidad] = planConCatalogo();
    $servicio->repuestos()->update(['articulo_id' => null]);

    actingAs(mecanico())
        ->post(route('mantenimiento.ordenes.store'), [
            'vehiculo_id' => $unidad->id,
            'tipo' => 'preventiva',
            'servicio_mantenimiento_id' => $servicio->id,
            'estado' => 'finalizada',
            'fecha' => '2026-11-05',
            'kilometraje' => 50000,
            'trabajo' => 'Servicio X',
        ])
        ->assertSessionHasErrors('justificacion_materiales');

    expect(OrdenTrabajo::query()->count())->toBe(0)
        ->and(session('errors')->first('justificacion_materiales'))->toContain('sin artículo de almacén ligado');
});

it('does not ask a correctiva for parts', function (): void {
    $orden = OrdenTrabajo::factory()->create(['estado' => EstadoOrdenTrabajo::Pendiente, 'fecha' => '2026-11-05']);

    actingAs(mecanico())
        ->put(route('mantenimiento.ordenes.update', $orden), finalizar($orden))
        ->assertSessionHasNoErrors();

    expect($orden->refresh()->estado)->toBe(EstadoOrdenTrabajo::Finalizada);
});

it('does not issue to a finished or voided orden', function (): void {
    $filtro = Articulo::factory()->create();
    entraAlAlmacen($filtro, 5);
    $finalizada = OrdenTrabajo::factory()->create(['estado' => EstadoOrdenTrabajo::Finalizada]);
    $anulada = OrdenTrabajo::factory()->create(['estado' => EstadoOrdenTrabajo::Pendiente, 'anulado_at' => now()]);

    foreach ([$finalizada, $anulada] as $orden) {
        actingAs(actorConRol('admin'))
            ->post(route('almacen.salidas.store'), [
                'fecha' => '2026-11-06',
                'entregado_a' => 'Juan Quispe',
                'destino' => 'orden_trabajo',
                'orden_trabajo_id' => $orden->id,
                'detalles' => [['articulo_id' => $filtro->id, 'cantidad' => 1]],
            ])
            ->assertSessionHasErrors('orden_trabajo_id');
    }

    expect(SalidaAlmacen::query()->count())->toBe(0);
});

// ─── Anular en vez de borrar ────────────────────────────────────────────────

it('voids an orden instead of deleting it, and the plan stops counting it', function (): void {
    [$servicio, , , $unidad] = planConCatalogo();
    $orden = OrdenTrabajo::factory()->create([
        'vehiculo_id' => $unidad->id,
        'tipo' => TipoOrdenTrabajo::Preventiva,
        'servicio_mantenimiento_id' => $servicio->id,
        'estado' => EstadoOrdenTrabajo::Finalizada,
        'kilometraje' => 50000,
    ]);

    expect(app(ProximoMantenimiento::class)->paraVehiculo($unidad)['proximo']['proximo_km'])->toBe(70000);

    actingAs(actorConRol('admin'))
        ->post(route('mantenimiento.ordenes.anular', $orden), ['motivo' => 'Se registró en la unidad equivocada'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('mantenimiento.show', $unidad));

    expect($orden->refresh()->estaAnulado())->toBeTrue()
        ->and($orden->motivo_anulacion)->toBe('Se registró en la unidad equivocada')
        ->and(app(ProximoMantenimiento::class)->paraVehiculo($unidad)['proximo'])->toBeNull();
});

it('keeps voiding for whoever has its permission, and has no delete route', function (): void {
    $orden = OrdenTrabajo::factory()->create();

    actingAs(mecanico())
        ->post(route('mantenimiento.ordenes.anular', $orden), ['motivo' => 'No corresponde'])
        ->assertForbidden();

    actingAs(actorConRol('admin'))
        ->delete("/mantenimiento/ordenes/{$orden->id}")
        ->assertMethodNotAllowed();

    expect($orden->refresh()->estaAnulado())->toBeFalse();
});

it('does not void an orden that still has warehouse issues', function (): void {
    $filtro = Articulo::factory()->create();
    entraAlAlmacen($filtro, 5);
    $orden = OrdenTrabajo::factory()->create(['estado' => EstadoOrdenTrabajo::EnProceso]);
    $vale = valeParaOrden($orden, [[$filtro, 2]]);

    actingAs(actorConRol('admin'))
        ->post(route('mantenimiento.ordenes.anular', $orden), ['motivo' => 'Se duplicó la orden'])
        ->assertSessionHasErrors('motivo');

    expect($orden->refresh()->estaAnulado())->toBeFalse();

    actingAs(actorConRol('admin'))
        ->post(route('almacen.salidas.anular', $vale), ['motivo' => 'La orden se duplicó'])
        ->assertSessionHasNoErrors();

    actingAs(actorConRol('admin'))
        ->post(route('mantenimiento.ordenes.anular', $orden), ['motivo' => 'Se duplicó la orden'])
        ->assertSessionHasNoErrors();

    expect($orden->refresh()->estaAnulado())->toBeTrue()
        ->and((float) $filtro->refresh()->stock)->toBe(5.0);
});

it('does not let taller edit a voided orden', function (): void {
    $orden = OrdenTrabajo::factory()->create(['anulado_at' => now()]);

    actingAs(mecanico())
        ->get(route('mantenimiento.ordenes.edit', $orden))
        ->assertForbidden();
});

// ─── Costo e historial ──────────────────────────────────────────────────────

it('shows on the orden what was issued to it and what it cost, only to who sees costs', function (): void {
    [$servicio, $filtro, $aceite, $unidad] = planConCatalogo();
    entraAlAlmacen($filtro, 5, 40);
    entraAlAlmacen($aceite, 20, 12.5);
    $orden = preventivaAbierta($unidad, $servicio);
    valeParaOrden($orden, [[$filtro, 1], [$aceite, 2]]);

    actingAs(actorConRol('admin'))
        ->get(route('mantenimiento.ordenes.edit', $orden))
        ->assertInertia(fn (Assert $page) => $page
            ->component('mantenimiento/orden')
            ->where('materiales.costo', 65)
            ->has('materiales.lineas', 2)
            ->has('materiales.requeridos', 3)
            ->where('materiales.requeridos.0.falta', false)
            ->where('materiales.requeridos.2.es_servicio', true)
            ->where('materiales.puede_despachar', true));

    actingAs(mecanico())
        ->get(route('mantenimiento.ordenes.edit', $orden))
        ->assertInertia(fn (Assert $page) => $page
            ->where('materiales.costo', null)
            ->where('materiales.lineas.0.costo', null)
            ->where('materiales.puede_despachar', false));
});

it('adds up on the unit everything the warehouse put on it', function (): void {
    [$servicio, $filtro, $aceite, $unidad] = planConCatalogo();
    entraAlAlmacen($filtro, 5, 40);
    entraAlAlmacen($aceite, 20, 12.5);
    $orden = preventivaAbierta($unidad, $servicio);
    valeParaOrden($orden, [[$filtro, 1]]);

    // Y un vale directo a la unidad, sin orden.
    actingAs(actorConRol('admin'))
        ->post(route('almacen.salidas.store'), [
            'fecha' => '2026-11-07',
            'entregado_a' => 'Juan Quispe',
            'destino' => 'unidad',
            'vehiculo_id' => $unidad->id,
            'detalles' => [['articulo_id' => $aceite->id, 'cantidad' => 4]],
        ])
        ->assertSessionHasNoErrors();

    actingAs(actorConRol('admin'))
        ->get(route('mantenimiento.show', $unidad))
        ->assertInertia(fn (Assert $page) => $page
            ->where('costo_almacen', 90)
            ->has('consumos', 2)
            ->where('ordenes.0.costo', 40)
            ->where('ordenes.0.numero', $orden->numero()));
});

// ─── Faltantes antes del preventivo ─────────────────────────────────────────

it('warns on the semáforo when the warehouse lacks parts for a service that is due', function (): void {
    [$servicio, $filtro, $aceite, $unidad] = planConCatalogo();
    entraAlAlmacen($filtro, 5);
    entraAlAlmacen($aceite, 1);
    OrdenTrabajo::factory()->create([
        'vehiculo_id' => $unidad->id,
        'tipo' => TipoOrdenTrabajo::Preventiva,
        'servicio_mantenimiento_id' => $servicio->id,
        'estado' => EstadoOrdenTrabajo::Finalizada,
        'kilometraje' => 50000,
    ]);
    OrdenTrabajo::factory()->create(['vehiculo_id' => $unidad->id, 'kilometraje' => 71000]);

    $falta = fn (): int => actingAs(actorConRol('admin'))
        ->get(route('mantenimiento.preventivo'))
        ->viewData('page')['props']['unidades'][0]['faltan_repuestos'];

    // Pide 2 litros y hay 1: falta el aceite, no el filtro ni la calibración.
    expect($falta())->toBe(1);

    entraAlAlmacen($aceite, 5);

    expect($falta())->toBe(0);
});

it('prefills the issue with what the preventiva still needs and the stock allows', function (): void {
    [$servicio, $filtro, $aceite, $unidad] = planConCatalogo();
    entraAlAlmacen($filtro, 5);
    entraAlAlmacen($aceite, 1.5);
    $orden = preventivaAbierta($unidad, $servicio);

    actingAs(actorConRol('admin'))
        ->get(route('almacen.salidas.create', ['orden' => $orden->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('inicial.orden_trabajo_id', $orden->id)
            ->where('inicial.detalles', [
                ['articulo_id' => $filtro->id, 'cantidad' => 1],
                ['articulo_id' => $aceite->id, 'cantidad' => 1.5],
            ]));
});

// ─── El plan apunta al catálogo ─────────────────────────────────────────────

it('links each plan item to a warehouse article or marks it as a service', function (): void {
    [$servicio, $filtro] = planConCatalogo();
    $plan = $servicio->plan;
    $datos = [
        'nombre' => $plan->nombre,
        'servicios' => [[
            'codigo' => 'X',
            'nombre' => 'Servicio X',
            'intervalo_km' => 20000,
            'intervalo_meses' => null,
            'repuestos' => [
                ['descripcion' => 'Filtro de aceite', 'codigo' => null, 'cantidad' => 1, 'unidad' => 'und', 'articulo_id' => $filtro->id, 'es_servicio' => false],
                ['descripcion' => 'Mano de obra', 'codigo' => null, 'cantidad' => null, 'unidad' => null, 'articulo_id' => null, 'es_servicio' => true],
            ],
        ]],
    ];

    actingAs(actorConRol('admin'))
        ->put(route('mantenimiento.planes.update', $plan), $datos)
        ->assertSessionHasNoErrors();

    $repuestos = $servicio->repuestos()->orderBy('orden')->get();

    expect($repuestos)->toHaveCount(2)
        ->and($repuestos[0]->articulo_id)->toBe($filtro->id)
        ->and($repuestos[1]->es_servicio)->toBeTrue();

    $datos['servicios'][0]['repuestos'][1]['articulo_id'] = $filtro->id;

    actingAs(actorConRol('admin'))
        ->put(route('mantenimiento.planes.update', $plan), $datos)
        ->assertSessionHasErrors('servicios.0.repuestos.1.articulo_id');
});

it('marks the services of the loaded plans and leaves the rest to be linked', function (): void {
    $internacional = PlanMantenimiento::deMarca('INTERNATIONAL');

    $calibracion = $internacional->servicios()->where('codigo', 'M')->sole()
        ->repuestos()->where('descripcion', 'like', 'Calibración%')->sole();

    expect($calibracion->es_servicio)->toBeTrue()
        ->and($calibracion->articulo_id)->toBeNull();
});
