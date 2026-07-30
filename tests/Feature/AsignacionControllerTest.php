<?php

use App\Enums\TipoDocumento;
use App\Models\Asignacion;
use App\Models\Conductor;
use App\Models\Vehiculo;
use Illuminate\Database\QueryException;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    foreach (['admin', 'visor', 'conductor'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function datosAsignacion(array $overrides = []): array
{
    return array_merge([
        'conductor_id' => Conductor::factory()->create()->id,
        'tracto_id' => Vehiculo::factory()->create()->id,
        'carreta_id' => Vehiculo::factory()->carreta()->create()->id,
        'observaciones' => null,
    ], $overrides);
}

it('redirects guests to login', function (): void {
    $this->get(route('asignaciones.index'))->assertRedirect(route('login'));
});

it('lets admins and viewers see the list', function (): void {
    $asignacion = Asignacion::factory()->create();

    actingAs(actorConRol('visor'))
        ->get(route('asignaciones.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('asignaciones/index')
            ->has('asignaciones.data', 1)
            ->where('asignaciones.data.0.tracto.placa', $asignacion->tracto->placa)
            ->where('asignaciones.data.0.conductor.telefono', $asignacion->conductor->telefono)
            ->where('asignaciones.data.0.vigente', true)
        );
});

it('lists only current assignments by default', function (): void {
    Asignacion::factory()->create();
    Asignacion::factory()->finalizada()->create();

    actingAs(actorConRol('admin'))
        ->get(route('asignaciones.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('asignaciones.data', 1)
            ->where('asignaciones.data.0.vigente', true)
        );
});

it('lists closed assignments under the historial filter', function (): void {
    Asignacion::factory()->create();
    $finalizada = Asignacion::factory()->finalizada()->create();

    actingAs(actorConRol('admin'))
        ->get(route('asignaciones.index', ['estado' => 'historial']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('asignaciones.data', 1)
            ->where('asignaciones.data.0.id', $finalizada->id)
            ->where('asignaciones.data.0.vigente', false)
        );
});

it('filters the list by placa', function (): void {
    $buscada = Asignacion::factory()->create([
        'tracto_id' => Vehiculo::factory()->create(['placa' => 'BJF-934'])->id,
    ]);
    Asignacion::factory()->create();

    actingAs(actorConRol('admin'))
        ->get(route('asignaciones.index', ['buscar' => 'BJF']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('asignaciones.data', 1)
            ->where('asignaciones.data.0.id', $buscada->id)
        );
});

it('reports which fierros and conductores are free', function (): void {
    Asignacion::factory()->create();
    $tractoLibre = Vehiculo::factory()->create(['placa' => 'LIB-001']);
    $conductorLibre = Conductor::factory()->create();

    actingAs(actorConRol('admin'))
        ->get(route('asignaciones.disponibles'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            // La carreta de la unidad armada no está libre, así que no sale.
            ->where('tractos.0.placa', $tractoLibre->placa)
            ->has('carretas', 0)
            ->where('conductores.0.nombre_completo', $conductorLibre->nombre_completo)
        );
});

it('offers only unassigned options on the create form', function (): void {
    Asignacion::factory()->create();
    $tractoLibre = Vehiculo::factory()->create();
    $conductorLibre = Conductor::factory()->create();

    actingAs(actorConRol('admin'))
        ->get(route('asignaciones.create'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('asignaciones/create')
            ->has('tractos', 1)
            ->where('tractos.0.id', $tractoLibre->id)
            ->has('conductores', 1)
            ->where('conductores.0.id', $conductorLibre->id)
            ->has('carretas', 0)
        );
});

it('leaves out vehicles that were dados de baja', function (): void {
    Vehiculo::factory()->create(['estado' => 'dado_de_baja']);
    $enMantenimiento = Vehiculo::factory()->enMantenimiento()->create();

    actingAs(actorConRol('admin'))
        ->get(route('asignaciones.create'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('tractos', 1)
            ->where('tractos.0.id', $enMantenimiento->id)
        );
});

it('stores an assignment and stamps the start date', function (): void {
    $datos = datosAsignacion();

    actingAs(actorConRol('admin'))
        ->post(route('asignaciones.store'), $datos)
        ->assertRedirect(route('asignaciones.index'));

    $asignacion = Asignacion::sole();

    expect($asignacion->conductor_id)->toBe($datos['conductor_id'])
        ->and($asignacion->tracto_id)->toBe($datos['tracto_id'])
        ->and($asignacion->carreta_id)->toBe($datos['carreta_id'])
        ->and($asignacion->desde->toDateString())->toBe(now()->toDateString())
        ->and($asignacion->hasta)->toBeNull();
});

it('stores an assignment without carreta', function (): void {
    actingAs(actorConRol('admin'))
        ->post(route('asignaciones.store'), datosAsignacion(['carreta_id' => null]))
        ->assertSessionHasNoErrors();

    expect(Asignacion::sole()->carreta_id)->toBeNull();
});

it('rejects a conductor that already has a unit', function (): void {
    $vigente = Asignacion::factory()->create();

    actingAs(actorConRol('admin'))
        ->post(route('asignaciones.store'), datosAsignacion([
            'conductor_id' => $vigente->conductor_id,
        ]))
        ->assertSessionHasErrors('conductor_id');

    expect(Asignacion::count())->toBe(1);
});

it('rejects a tracto that is already assigned', function (): void {
    $vigente = Asignacion::factory()->create();

    actingAs(actorConRol('admin'))
        ->post(route('asignaciones.store'), datosAsignacion([
            'tracto_id' => $vigente->tracto_id,
        ]))
        ->assertSessionHasErrors('tracto_id');
});

it('rejects a carreta that is already assigned', function (): void {
    $vigente = Asignacion::factory()->create();

    actingAs(actorConRol('admin'))
        ->post(route('asignaciones.store'), datosAsignacion([
            'carreta_id' => $vigente->carreta_id,
        ]))
        ->assertSessionHasErrors('carreta_id');
});

it('frees the fierros once the assignment is closed', function (): void {
    $cerrada = Asignacion::factory()->finalizada()->create();

    actingAs(actorConRol('admin'))
        ->post(route('asignaciones.store'), datosAsignacion([
            'conductor_id' => $cerrada->conductor_id,
            'tracto_id' => $cerrada->tracto_id,
            'carreta_id' => $cerrada->carreta_id,
        ]))
        ->assertSessionHasNoErrors();

    expect(Asignacion::count())->toBe(2);
});

it('rejects a carreta in the tracto slot', function (): void {
    actingAs(actorConRol('admin'))
        ->post(route('asignaciones.store'), datosAsignacion([
            'tracto_id' => Vehiculo::factory()->carreta()->create()->id,
        ]))
        ->assertSessionHasErrors('tracto_id');
});

it('rejects a tracto in the carreta slot', function (): void {
    actingAs(actorConRol('admin'))
        ->post(route('asignaciones.store'), datosAsignacion([
            'carreta_id' => Vehiculo::factory()->create()->id,
        ]))
        ->assertSessionHasErrors('carreta_id');
});

it('rejects an inactive conductor', function (): void {
    actingAs(actorConRol('admin'))
        ->post(route('asignaciones.store'), datosAsignacion([
            'conductor_id' => Conductor::factory()->inactivo()->create()->id,
        ]))
        ->assertSessionHasErrors('conductor_id');
});

it('forbids viewers from creating assignments', function (): void {
    actingAs(actorConRol('visor'))
        ->post(route('asignaciones.store'), datosAsignacion())
        ->assertForbidden();

    expect(Asignacion::count())->toBe(0);
});

it('updates an assignment and keeps it current', function (): void {
    $asignacion = Asignacion::factory()->create();
    $otraCarreta = Vehiculo::factory()->carreta()->create();

    actingAs(actorConRol('admin'))
        ->put(route('asignaciones.update', $asignacion), [
            'conductor_id' => $asignacion->conductor_id,
            'tracto_id' => $asignacion->tracto_id,
            'carreta_id' => $otraCarreta->id,
            'desde' => now()->subDays(3)->toDateString(),
            'observaciones' => 'Cambió de carreta.',
        ])
        ->assertRedirect(route('asignaciones.index'));

    $asignacion->refresh();

    expect($asignacion->carreta_id)->toBe($otraCarreta->id)
        ->and($asignacion->desde->toDateString())->toBe(now()->subDays(3)->toDateString())
        ->and($asignacion->hasta)->toBeNull();
});

it('keeps the edit form usable for the already assigned fierros', function (): void {
    $asignacion = Asignacion::factory()->create();

    actingAs(actorConRol('admin'))
        ->get(route('asignaciones.edit', $asignacion))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('asignaciones/edit')
            ->where('asignacion.tracto_placa', $asignacion->tracto->placa)
            ->where('tractos.0.id', $asignacion->tracto_id)
            ->where('conductores.0.id', $asignacion->conductor_id)
            ->where('carretas.0.id', $asignacion->carreta_id)
        );
});

it('does not let closed assignments be edited', function (): void {
    $cerrada = Asignacion::factory()->finalizada()->create();

    actingAs(actorConRol('admin'))
        ->get(route('asignaciones.edit', $cerrada))
        ->assertForbidden();
});

it('closes the assignment when the unit is liberada', function (): void {
    $asignacion = Asignacion::factory()->create();

    actingAs(actorConRol('admin'))
        ->post(route('asignaciones.liberar', $asignacion))
        ->assertRedirect(route('asignaciones.create', ['tracto' => $asignacion->tracto_id]));

    expect($asignacion->refresh()->hasta?->toDateString())->toBe(now()->toDateString());
});

it('forbids viewers from liberating a unit', function (): void {
    $asignacion = Asignacion::factory()->create();

    actingAs(actorConRol('visor'))
        ->post(route('asignaciones.liberar', $asignacion))
        ->assertForbidden();

    expect($asignacion->refresh()->hasta)->toBeNull();
});

it('preselects the tracto after liberating so it can be reassigned', function (): void {
    $tracto = Vehiculo::factory()->create();

    actingAs(actorConRol('admin'))
        ->get(route('asignaciones.create', ['tracto' => $tracto->id]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('preseleccion.tracto_id', $tracto->id)
        );
});

it('shows the reasignar form with only the tractos and carretas libres', function (): void {
    $asignacion = Asignacion::factory()->create();
    $libre = Vehiculo::factory()->create();
    // El tracto de otra unidad vigente no debe ofrecerse: ya tiene dueño.
    Asignacion::factory()->create();

    actingAs(actorConRol('admin'))
        ->get(route('asignaciones.reasignar.form', $asignacion))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('asignaciones/reasignar')
            ->where('asignacion.conductor_nombre', $asignacion->conductor->nombre_completo)
            ->where('asignacion.tracto_placa', $asignacion->tracto->placa)
            ->where('tractos', fn ($tractos) => collect($tractos)
                ->pluck('id')
                ->contains($libre->id))
        );
});

it('moves the conductor to another tracto in one step', function (): void {
    $asignacion = Asignacion::factory()->create();
    $nuevoTracto = Vehiculo::factory()->create();

    actingAs(actorConRol('admin'))
        ->patch(route('asignaciones.reasignar', $asignacion), [
            'tracto_id' => $nuevoTracto->id,
        ])
        ->assertRedirect(route('asignaciones.index'));

    expect($asignacion->refresh()->hasta?->toDateString())->toBe(now()->toDateString())
        ->and(Asignacion::query()->vigentes()->where('conductor_id', $asignacion->conductor_id)->first())
        ->tracto_id->toBe($nuevoTracto->id);
});

it('carries the same carreta when reasigning if it is chosen again', function (): void {
    $carreta = Vehiculo::factory()->carreta()->create();
    $asignacion = Asignacion::factory()->create(['carreta_id' => $carreta->id]);
    $nuevoTracto = Vehiculo::factory()->create();

    actingAs(actorConRol('admin'))
        ->patch(route('asignaciones.reasignar', $asignacion), [
            'tracto_id' => $nuevoTracto->id,
            'carreta_id' => $carreta->id,
        ])
        ->assertRedirect();

    expect(Asignacion::query()->vigentes()->where('conductor_id', $asignacion->conductor_id)->first()->carreta_id)
        ->toBe($carreta->id);
});

it('rejects reasigning to a tracto that already has another conductor', function (): void {
    $asignacion = Asignacion::factory()->create();
    $ocupado = Asignacion::factory()->create();

    actingAs(actorConRol('admin'))
        ->patch(route('asignaciones.reasignar', $asignacion), [
            'tracto_id' => $ocupado->tracto_id,
        ])
        ->assertSessionHasErrors('tracto_id');

    expect($asignacion->refresh()->hasta)->toBeNull();
});

it('forbids viewers from reasigning a conductor', function (): void {
    $asignacion = Asignacion::factory()->create();
    $nuevoTracto = Vehiculo::factory()->create();

    actingAs(actorConRol('visor'))
        ->patch(route('asignaciones.reasignar', $asignacion), ['tracto_id' => $nuevoTracto->id])
        ->assertForbidden();

    expect($asignacion->refresh()->hasta)->toBeNull();
});

it('does not let a closed assignment be reasigned', function (): void {
    $cerrada = Asignacion::factory()->finalizada()->create();
    $nuevoTracto = Vehiculo::factory()->create();

    actingAs(actorConRol('admin'))
        ->patch(route('asignaciones.reasignar', $cerrada), ['tracto_id' => $nuevoTracto->id])
        ->assertForbidden();
});

it('deletes an assignment', function (): void {
    $asignacion = Asignacion::factory()->create();

    actingAs(actorConRol('admin'))
        ->delete(route('asignaciones.destroy', $asignacion))
        ->assertRedirect(route('asignaciones.index'));

    expect(Asignacion::count())->toBe(0);
});

it('blocks duplicate current assignments at the database level', function (): void {
    $vigente = Asignacion::factory()->create();

    expect(fn () => Asignacion::factory()->create([
        'tracto_id' => $vigente->tracto_id,
    ]))->toThrow(QueryException::class);
});

it('resume en la unidad el peor semáforo de sus dos fierros', function (): void {
    $asignacion = Asignacion::factory()->create();

    // El tracto queda impecable y la carreta sin ningún papel.
    foreach ($asignacion->tracto->tipo->documentosObligatorios() as $tipo) {
        $asignacion->tracto->documentos()->create([
            'tipo' => $tipo,
            'fecha_vencimiento' => now()->addYear()->format('Y-m-d'),
        ]);
    }

    actingAs(actorConRol('admin'))
        ->get(route('asignaciones.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('asignaciones.data.0.documentacion.semaforo', 'rojo')
            ->where('asignaciones.data.0.documentacion.faltantes', [
                'Carreta: Tarjeta de propiedad',
                'Carreta: Revisión técnica de mercancías',
                'Carreta: TUC (habilitación MTC)',
                'Carreta: MATPEL (materiales peligrosos)',
            ])
        );
});

it('no penaliza a la unidad que anda sin carreta', function (): void {
    $asignacion = Asignacion::factory()->sinCarreta()->create();

    foreach ($asignacion->tracto->tipo->documentosObligatorios() as $tipo) {
        $asignacion->tracto->documentos()->create([
            'tipo' => $tipo,
            'fecha_vencimiento' => now()->addYear()->format('Y-m-d'),
        ]);
    }

    actingAs(actorConRol('admin'))
        ->get(route('asignaciones.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('asignaciones.data.0.documentacion.semaforo', 'verde')
        );
});

it('rejects a tracto dado de baja even on a direct POST', function (): void {
    $dadoDeBaja = Vehiculo::factory()->create(['estado' => 'dado_de_baja']);

    actingAs(actorConRol('admin'))
        ->post(route('asignaciones.store'), datosAsignacion([
            'tracto_id' => $dadoDeBaja->id,
        ]))
        ->assertSessionHasErrors('tracto_id');

    expect(Asignacion::count())->toBe(0);
});

it('still lets the carreta be corrected when the conductor went inactive', function (): void {
    $asignacion = Asignacion::factory()->create();
    $asignacion->conductor->update(['activo' => false]);
    $otraCarreta = Vehiculo::factory()->carreta()->create();

    actingAs(actorConRol('admin'))
        ->put(route('asignaciones.update', $asignacion), [
            'conductor_id' => $asignacion->conductor_id,
            'tracto_id' => $asignacion->tracto_id,
            'carreta_id' => $otraCarreta->id,
            'desde' => $asignacion->desde->toDateString(),
            'observaciones' => null,
        ])
        ->assertSessionHasNoErrors();

    expect($asignacion->refresh()->carreta_id)->toBe($otraCarreta->id);
});

it('keeps showing the placa of a tracto that left the fleet', function (): void {
    $cerrada = Asignacion::factory()->finalizada()->create();
    $placa = $cerrada->tracto->placa;

    $cerrada->tracto->delete();

    actingAs(actorConRol('admin'))
        ->get(route('asignaciones.index', ['estado' => 'historial']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('asignaciones.data', 1)
            ->where('asignaciones.data.0.tracto.placa', $placa)
        );
});

it('filters the list by the caja of the tracto', function (): void {
    $mecanico = Asignacion::factory()->create([
        'tracto_id' => Vehiculo::factory()->create(['caja' => 'mecanica'])->id,
    ]);
    Asignacion::factory()->create([
        'tracto_id' => Vehiculo::factory()->create(['caja' => 'automatica'])->id,
    ]);

    actingAs(actorConRol('admin'))
        ->get(route('asignaciones.index', ['caja' => 'mecanica']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('asignaciones.data', 1)
            ->where('asignaciones.data.0.id', $mecanico->id)
            ->has('cajas', 2)
        );
});

it('exposes the TUC of the tracto for copying', function (): void {
    $asignacion = Asignacion::factory()->create();

    $asignacion->tracto->documentos()->create([
        'tipo' => 'habilitacion_mtc',
        'numero' => '21M22000405E',
        'fecha_vencimiento' => now()->addYear()->format('Y-m-d'),
    ]);

    actingAs(actorConRol('admin'))
        ->get(route('asignaciones.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('asignaciones.data.0.tracto.tuc_numero', '21M22000405E')
        );
});

it('summarises the unit paperwork instead of listing every document', function (): void {
    Asignacion::factory()->create();

    actingAs(actorConRol('admin'))
        ->get(route('asignaciones.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            // El listado solo lleva el resumen: un chip por documento
            // ensanchaba la tabla de más, sobre todo en móvil.
            ->missing('asignaciones.data.0.documentos')
            ->where('asignaciones.data.0.documentacion.semaforo', 'rojo')
            // 5 obligatorios del tracto + 4 de la carreta, todos sin cargar.
            ->has('asignaciones.data.0.documentacion.faltantes', 9)
        );
});

it('counts only the tracto paperwork when the unit has no carreta', function (): void {
    Asignacion::factory()->sinCarreta()->create();

    actingAs(actorConRol('admin'))
        ->get(route('asignaciones.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('asignaciones.data.0.documentacion.faltantes', 5)
        );
});

it('exposes the TUC of both the tracto and the carreta', function (): void {
    $asignacion = Asignacion::factory()->create();

    foreach ([$asignacion->tracto, $asignacion->carreta] as $indice => $fierro) {
        $fierro->documentos()->create([
            'tipo' => TipoDocumento::HabilitacionMtc,
            'numero' => "TUC-{$indice}",
            'fecha_vencimiento' => '2028-02-05',
        ]);
    }

    actingAs(actorConRol('admin'))
        ->get(route('asignaciones.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('asignaciones.data.0.tracto.tuc_numero', 'TUC-0')
            ->where('asignaciones.data.0.carreta.tuc_numero', 'TUC-1')
        );
});

it('leaves the carreta TUC null when the unit has no carreta', function (): void {
    Asignacion::factory()->sinCarreta()->create();

    actingAs(actorConRol('admin'))
        ->get(route('asignaciones.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('asignaciones.data.0.carreta', null)
        );
});

it('finds a unit by a placa typed without its hyphen', function (): void {
    $buscada = Asignacion::factory()->create([
        'tracto_id' => Vehiculo::factory()->create(['placa' => 'BJF-934'])->id,
    ]);
    Asignacion::factory()->create();

    actingAs(actorConRol('admin'))
        ->get(route('asignaciones.index', ['buscar' => 'BJF934']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('asignaciones.data', 1)
            ->where('asignaciones.data.0.id', $buscada->id)
        );
});

it('splits what is free into its own page', function (): void {
    $asignada = Asignacion::factory()->create();
    $tractoLibre = Vehiculo::factory()->create(['placa' => 'LIB-001']);
    $carretaLibre = Vehiculo::factory()->carreta()->create();
    $conductorLibre = Conductor::factory()->create();

    actingAs(actorConRol('visor'))
        ->get(route('asignaciones.disponibles'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('asignaciones/disponibles')
            ->has('tractos', 1)
            ->where('tractos.0.id', $tractoLibre->id)
            ->has('carretas', 1)
            ->where('carretas.0.id', $carretaLibre->id)
            ->has('conductores', 1)
            ->where('conductores.0.id', $conductorLibre->id)
        );

    // Lo que ya está en una unidad no aparece aquí.
    expect($asignada->tracto_id)->not->toBe($tractoLibre->id);
});

it('keeps the assignments list free of the disponibles panel', function (): void {
    Asignacion::factory()->create();
    Vehiculo::factory()->create();

    actingAs(actorConRol('admin'))
        ->get(route('asignaciones.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->missing('disponibles'));
});

it('leaves dados de baja out of what is free', function (): void {
    Vehiculo::factory()->create(['estado' => 'dado_de_baja']);
    $inactivo = Conductor::factory()->inactivo()->create();

    actingAs(actorConRol('admin'))
        ->get(route('asignaciones.disponibles'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('tractos', 0)
            ->has('conductores', 0)
        );

    expect($inactivo->activo)->toBeFalse();
});

it('carries the documentation of each free fierro and conductor', function (): void {
    $tracto = Vehiculo::factory()->create();
    $tracto->documentos()->create([
        'tipo' => 'habilitacion_mtc',
        'numero' => 'TUC-001',
        'fecha_vencimiento' => now()->addYear()->format('Y-m-d'),
    ]);

    actingAs(actorConRol('admin'))
        ->get(route('asignaciones.disponibles'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('tractos.0.tuc_numero', 'TUC-001')
            ->where('tractos.0.documentacion.semaforo', 'rojo')
            ->has('tractos.0.documentacion.documentos', 5)
        );
});

it('preselects whatever came from the sin asignar page', function (): void {
    $tracto = Vehiculo::factory()->create();
    $carreta = Vehiculo::factory()->carreta()->create();
    $conductor = Conductor::factory()->create();

    actingAs(actorConRol('admin'))
        ->get(route('asignaciones.create', [
            'tracto' => $tracto->id,
            'carreta' => $carreta->id,
            'conductor' => $conductor->id,
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('preseleccion.tracto_id', $tracto->id)
            ->where('preseleccion.carreta_id', $carreta->id)
            ->where('preseleccion.conductor_id', $conductor->id)
        );
});

it('forbids the disponibles page to guests', function (): void {
    $this->get(route('asignaciones.disponibles'))->assertRedirect(route('login'));
});
