<?php

use App\Enums\EstadoAsistencia;
use App\Enums\TipoDocumentoConductor;
use App\Models\Asistencia;
use App\Models\Conductor;
use App\Models\Viaje;
use Carbon\CarbonImmutable;
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
function datosConductor(array $overrides = []): array
{
    return array_merge([
        'nombres' => 'Juan Carlos',
        'apellidos' => 'García Pérez',
        'documento' => '12345678',
        'licencia' => 'Q12345678',
        'categoria_licencia' => 'A-IIIa',
        'licencia_vence' => now()->addYear()->format('Y-m-d'),
        'telefono' => '999888777',
        'email' => 'juan@ejemplo.com',
        'procedencia' => 'Puno',
        'activo' => true,
    ], $overrides);
}

it('redirects guests to login', function (): void {
    $this->get(route('conductores.index'))->assertRedirect(route('login'));
});

it('lets admins and viewers see the list', function (): void {
    $conductor = Conductor::factory()->create();

    actingAs(actorConRol('visor'))
        ->get(route('conductores.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('conductores/index')
            ->has('conductores.data', 1)
            ->where('conductores.data.0.nombre_completo', $conductor->nombre_completo)
        );
});

it('filters the list by licencia', function (): void {
    Conductor::factory()->create(['licencia' => 'Q11111111']);
    Conductor::factory()->create(['licencia' => 'Q22222222']);

    actingAs(actorConRol('admin'))
        ->get(route('conductores.index', ['buscar' => 'Q1111']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->has('conductores.data', 1));
});

it('filters the list by the full "apellidos nombres" name shown in the table', function (): void {
    Conductor::factory()->create(['nombres' => 'David', 'apellidos' => 'Vilca Choquehuanca']);
    Conductor::factory()->create(['nombres' => 'Martin', 'apellidos' => 'Noa Benavente']);

    actingAs(actorConRol('admin'))
        ->get(route('conductores.index', ['buscar' => 'Vilca Choquehuanca David']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->has('conductores.data', 1));
});

it('exposes the edit form with a date-only license expiry', function (): void {
    $conductor = Conductor::factory()->create([
        'licencia_vence' => '2030-05-15',
    ]);

    actingAs(actorConRol('admin'))
        ->get(route('conductores.edit', $conductor))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('conductores/edit')
            ->where('conductor.nombre_completo', $conductor->nombre_completo)
            ->where('conductor.licencia_vence', '2030-05-15')
        );
});

it('includes the full-year asistencia calendar for an admin, defaulting to the current year', function (): void {
    $conductor = Conductor::factory()->create();

    $this->travelTo(CarbonImmutable::parse('2026-08-15 12:00:00'));

    actingAs(actorConRol('admin'))
        ->get(route('conductores.show', $conductor))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('asistencia.anio', 2026)
            ->has('asistencia.calendarios', 12)
            ->where('asistencia.calendarios.0.mes', '2026-01-01')
            ->where('asistencia.calendarios.11.mes', '2026-12-01')
            ->where('asistencia.calendarios.0.dias_debidos', 0)
            ->where('asistencia.calendarios.0.notas', null)
        );
});

it('hides the asistencia calendar from a visor', function (): void {
    $conductor = Conductor::factory()->create();

    actingAs(actorConRol('visor'))
        ->get(route('conductores.show', $conductor))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->where('asistencia', null));
});

it('shows the requested year in the asistencia calendar', function (): void {
    $conductor = Conductor::factory()->create();

    actingAs(actorConRol('admin'))
        ->get(route('conductores.show', [$conductor, 'anio' => 2025]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('asistencia.anio', 2025)
            ->where('asistencia.calendarios.0.mes', '2025-01-01')
            ->where('asistencia.calendarios.11.mes', '2025-12-01')
        );
});

it('defaults to the current year in the asistencia calendar when anio is invalid', function (): void {
    $conductor = Conductor::factory()->create();

    $this->travelTo(CarbonImmutable::parse('2026-08-15 12:00:00'));

    actingAs(actorConRol('admin'))
        ->get(route('conductores.show', [$conductor, 'anio' => -5]))
        ->assertInertia(fn (Assert $page) => $page->where('asistencia.anio', 2026));
});

it('builds a full-week grid for a month in the asistencia calendar, padding with neighboring days', function (): void {
    $conductor = Conductor::factory()->create();

    actingAs(actorConRol('admin'))
        ->get(route('conductores.show', [$conductor, 'anio' => 2026]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('asistencia.calendarios.7.dias', 42)
            ->where('asistencia.calendarios.7.mes', '2026-08-01')
            // Agosto 2026 empieza en sábado: la grilla arranca el lunes
            // anterior (27 de julio) para completar la semana.
            ->where('asistencia.calendarios.7.dias.0.fecha', '2026-07-27')
            ->where('asistencia.calendarios.7.dias.0.es_relleno', true)
            ->where('asistencia.calendarios.7.dias.5.fecha', '2026-08-01')
            ->where('asistencia.calendarios.7.dias.5.es_relleno', false)
            ->where('asistencia.calendarios.7.dias.35.fecha', '2026-08-31')
            ->where('asistencia.calendarios.7.dias.35.es_relleno', false)
            // Agosto termina en lunes: la grilla sigue hasta el domingo
            // siguiente (6 de setiembre) para cerrar esa semana.
            ->where('asistencia.calendarios.7.dias.41.fecha', '2026-09-06')
            ->where('asistencia.calendarios.7.dias.41.es_relleno', true)
        );
});

it('only brings marks for the right month in the asistencia calendar', function (): void {
    $conductor = Conductor::factory()->create();
    $otro = Conductor::factory()->create();

    $asistencia = Asistencia::create([
        'conductor_id' => $conductor->id,
        'fecha' => '2026-08-10',
        'estado' => EstadoAsistencia::Vacaciones,
    ]);

    // Otro mes y otro conductor: ninguna debe aparecer en el de agosto.
    Asistencia::create([
        'conductor_id' => $conductor->id,
        'fecha' => '2026-07-30',
        'estado' => EstadoAsistencia::Falta,
    ]);
    Asistencia::create([
        'conductor_id' => $otro->id,
        'fecha' => '2026-08-10',
        'estado' => EstadoAsistencia::Falta,
    ]);

    actingAs(actorConRol('admin'))
        ->get(route('conductores.show', [$conductor, 'anio' => 2026]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('asistencia.calendarios.7.marcas.2026-08-10.estado', 'vacaciones')
            ->where('asistencia.calendarios.7.marcas.2026-08-10.asistencia_id', $asistencia->id)
            ->missing('asistencia.calendarios.7.marcas.2026-07-30')
            ->has('asistencia.calendarios.7.marcas', 1)
        );
});

it('reflects a mark and a removal made via AsistenciaController in the conductor asistencia calendar', function (): void {
    $conductor = Conductor::factory()->create();

    actingAs(actorConRol('admin'))
        ->patch(route('asistencia.marcar', $conductor), [
            'fecha' => '2026-08-10',
            'estado' => EstadoAsistencia::Asistencia->value,
        ])
        ->assertSessionHasNoErrors();

    $asistencia = Asistencia::query()->sole();

    actingAs(actorConRol('admin'))
        ->get(route('conductores.show', [$conductor, 'anio' => 2026]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('asistencia.calendarios.7.marcas.2026-08-10.asistencia_id', $asistencia->id)
            ->where('asistencia.calendarios.7.marcas.2026-08-10.estado', 'asistencia')
        );

    actingAs(actorConRol('admin'))
        ->delete(route('asistencia.destroy', $asistencia))
        ->assertSessionHasNoErrors();

    actingAs(actorConRol('admin'))
        ->get(route('conductores.show', [$conductor, 'anio' => 2026]))
        ->assertInertia(fn (Assert $page) => $page->missing('asistencia.calendarios.7.marcas.2026-08-10'));
});

it('surfaces dias_debidos and notas set for a month in the asistencia calendar', function (): void {
    $conductor = Conductor::factory()->create();

    actingAs(actorConRol('admin'))
        ->patch(route('asistencia.diasDebidos', $conductor), [
            'mes' => '2026-08-15',
            'dias_debidos' => 3,
        ])
        ->assertSessionHasNoErrors();

    actingAs(actorConRol('admin'))
        ->patch(route('asistencia.notas', $conductor), [
            'mes' => '2026-08-15',
            'notas' => 'Acordó reponer el 30/08 el día que faltó por trámite.',
        ])
        ->assertSessionHasNoErrors();

    actingAs(actorConRol('admin'))
        ->get(route('conductores.show', [$conductor, 'anio' => 2026]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('asistencia.calendarios.7.dias_debidos', 3)
            ->where('asistencia.calendarios.7.notas', 'Acordó reponer el 30/08 el día que faltó por trámite.')
        );
});

it('keeps dias_debidos independent per month in the asistencia calendar', function (): void {
    $conductor = Conductor::factory()->create();

    actingAs(actorConRol('admin'))
        ->patch(route('asistencia.diasDebidos', $conductor), ['mes' => '2026-08-01', 'dias_debidos' => 2])
        ->assertSessionHasNoErrors();

    actingAs(actorConRol('admin'))
        ->patch(route('asistencia.diasDebidos', $conductor), ['mes' => '2026-09-01', 'dias_debidos' => 6])
        ->assertSessionHasNoErrors();

    actingAs(actorConRol('admin'))
        ->get(route('conductores.show', [$conductor, 'anio' => 2026]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('asistencia.calendarios.7.dias_debidos', 2)
            ->where('asistencia.calendarios.8.dias_debidos', 6)
        );
});

it('includes only this conductor\'s matched trips, newest first, in the recent trips card', function (): void {
    $conductor = Conductor::factory()->create();
    $otro = Conductor::factory()->create();

    $viejo = Viaje::factory()->create([
        'conductor_id' => $conductor->id,
        'fecha_traslado' => '2026-07-01',
    ]);
    $nuevo = Viaje::factory()->create([
        'conductor_id' => $conductor->id,
        'fecha_traslado' => '2026-08-15',
    ]);

    // De otro conductor, o sin matchear contra el padrón: no deben salir acá.
    Viaje::factory()->create(['conductor_id' => $otro->id]);
    Viaje::factory()->create(['conductor_id' => null, 'conductor_dni' => $conductor->documento]);

    actingAs(actorConRol('admin'))
        ->get(route('conductores.show', $conductor))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('viajes', 2)
            ->where('viajes.0.id', $nuevo->id)
            ->where('viajes.1.id', $viejo->id)
        );
});

it('lets a visor see the recent trips of a conductor', function (): void {
    $conductor = Conductor::factory()->create();

    actingAs(actorConRol('visor'))
        ->get(route('conductores.show', $conductor))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->has('viajes'));
});

it('summarises trips, this month\'s attendance and documents in the ficha stats', function (): void {
    $conductor = Conductor::factory()->create();

    $this->travelTo(CarbonImmutable::parse('2026-08-20 12:00:00'));

    Viaje::factory()->create([
        'conductor_id' => $conductor->id,
        'fecha_traslado' => '2026-08-18',
    ]);
    Viaje::factory()->create([
        'conductor_id' => $conductor->id,
        'fecha_traslado' => '2026-07-02',
    ]);

    foreach (['2026-08-01', '2026-08-02', '2026-08-03'] as $fecha) {
        Asistencia::create([
            'conductor_id' => $conductor->id,
            'fecha' => $fecha,
            'estado' => EstadoAsistencia::Asistencia,
        ]);
    }

    Asistencia::create([
        'conductor_id' => $conductor->id,
        'fecha' => '2026-08-04',
        'estado' => EstadoAsistencia::Descanso,
    ]);
    Asistencia::create([
        'conductor_id' => $conductor->id,
        'fecha' => '2026-08-05',
        'estado' => EstadoAsistencia::Falta,
    ]);

    // Del mes pasado: no debe contar en los números del mes en curso.
    Asistencia::create([
        'conductor_id' => $conductor->id,
        'fecha' => '2026-07-15',
        'estado' => EstadoAsistencia::Asistencia,
    ]);

    actingAs(actorConRol('admin'))
        ->get(route('conductores.show', $conductor))
        ->assertInertia(fn (Assert $page) => $page
            ->where('estadisticas.viajes_totales', 2)
            ->where('estadisticas.ultimo_viaje', '2026-08-18')
            ->where('estadisticas.dias_trabajados_mes', 3)
            ->where('estadisticas.dias_descanso_mes', 1)
            ->where('estadisticas.faltas_mes', 1)
            ->where('estadisticas.documentos_vigentes', 0)
            // Del enum y no un número fijo: agregar un documento obligatorio
            // no debería romper un test que mide otra cosa.
            ->where('estadisticas.documentos_totales', count(TipoDocumentoConductor::obligatorios()))
        );
});

it('leaves the attendance stats null for a visor, who cannot see asistencia', function (): void {
    $conductor = Conductor::factory()->create();

    actingAs(actorConRol('visor'))
        ->get(route('conductores.show', $conductor))
        ->assertInertia(fn (Assert $page) => $page
            ->where('estadisticas.dias_trabajados_mes', null)
            ->where('estadisticas.dias_descanso_mes', null)
            ->where('estadisticas.faltas_mes', null)
            ->where('estadisticas.viajes_totales', 0)
        );
});

it('forbids drivers from the list', function (): void {
    actingAs(actorConRol('conductor'))
        ->get(route('conductores.index'))
        ->assertForbidden();
});

it('allows an admin to create a conductor', function (): void {
    actingAs(actorConRol('admin'))
        ->post(route('conductores.store'), datosConductor())
        ->assertRedirect(route('conductores.index'));

    $this->assertDatabaseHas('conductores', ['documento' => '12345678']);
});

it('forbids a viewer from creating a conductor', function (): void {
    actingAs(actorConRol('visor'))
        ->post(route('conductores.store'), datosConductor())
        ->assertForbidden();

    $this->assertDatabaseCount('conductores', 0);
});

it('validates required fields when creating', function (): void {
    actingAs(actorConRol('admin'))
        ->post(route('conductores.store'), datosConductor([
            'nombres' => '',
            'apellidos' => '',
            'documento' => '',
        ]))
        ->assertSessionHasErrors(['nombres', 'apellidos', 'documento']);
});

it('rejects a duplicate documento', function (): void {
    Conductor::factory()->create(['documento' => '12345678']);

    actingAs(actorConRol('admin'))
        ->post(route('conductores.store'), datosConductor(['documento' => '12345678']))
        ->assertSessionHasErrors('documento');
});

it('allows an admin to update a conductor', function (): void {
    $conductor = Conductor::factory()->create();

    actingAs(actorConRol('admin'))
        ->put(route('conductores.update', $conductor), datosConductor([
            'nombres' => 'Carlos Editado',
        ]))
        ->assertRedirect(route('conductores.index'));

    expect($conductor->fresh()->nombres)->toBe('Carlos Editado');
});

it('keeps its own documento when updating', function (): void {
    $conductor = Conductor::factory()->create(['documento' => '87654321']);

    actingAs(actorConRol('admin'))
        ->put(route('conductores.update', $conductor), datosConductor([
            'documento' => '87654321',
            'nombres' => 'Nuevo Nombre',
        ]))
        ->assertSessionHasNoErrors();

    expect($conductor->fresh()->nombres)->toBe('Nuevo Nombre');
});

it('deletes a conductor', function (): void {
    $conductor = Conductor::factory()->create();

    actingAs(actorConRol('admin'))
        ->delete(route('conductores.destroy', $conductor))
        ->assertRedirect(route('conductores.index'));

    $this->assertDatabaseMissing('conductores', ['id' => $conductor->id]);
});

it('forbids a viewer from deleting a conductor', function (): void {
    $conductor = Conductor::factory()->create();

    actingAs(actorConRol('visor'))
        ->delete(route('conductores.destroy', $conductor))
        ->assertForbidden();

    $this->assertDatabaseHas('conductores', ['id' => $conductor->id]);
});

it('requires a motivo when deactivating a conductor', function (): void {
    $conductor = Conductor::factory()->create(['activo' => true]);

    actingAs(actorConRol('admin'))
        ->put(route('conductores.update', $conductor), datosConductor([
            'activo' => false,
            'motivo_baja' => '',
        ]))
        ->assertSessionHasErrors('motivo_baja');

    expect($conductor->fresh()->activo)->toBeTrue();
});

it('defaults fecha_baja to today when deactivating without one', function (): void {
    $conductor = Conductor::factory()->create(['activo' => true]);

    $this->travelTo('2026-09-04 12:00:00');

    actingAs(actorConRol('admin'))
        ->put(route('conductores.update', $conductor), datosConductor([
            'activo' => false,
            'motivo_baja' => 'Renuncia voluntaria',
        ]))
        ->assertSessionHasNoErrors();

    $conductor->refresh();

    expect($conductor->activo)->toBeFalse()
        ->and($conductor->fecha_baja->toDateString())->toBe('2026-09-04')
        ->and($conductor->motivo_baja)->toBe('Renuncia voluntaria');
});

it('clears fecha_baja and motivo_baja when reactivating a conductor', function (): void {
    $conductor = Conductor::factory()->create([
        'activo' => false,
        'fecha_baja' => '2026-08-01',
        'motivo_baja' => 'Renuncia voluntaria',
    ]);

    actingAs(actorConRol('admin'))
        ->put(route('conductores.update', $conductor), datosConductor([
            'activo' => true,
        ]))
        ->assertSessionHasNoErrors();

    $conductor->refresh();

    expect($conductor->activo)->toBeTrue()
        ->and($conductor->fecha_baja)->toBeNull()
        ->and($conductor->motivo_baja)->toBeNull();
});

it('serves the full year of asistencia on its own page', function (): void {
    $conductor = Conductor::factory()->create();

    $this->travelTo(CarbonImmutable::parse('2026-08-15 12:00:00'));

    actingAs(actorConRol('admin'))
        ->get(route('conductores.asistencia', $conductor))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('conductores/asistencia')
            ->where('conductor.id', $conductor->id)
            ->where('asistencia.anio', 2026)
            ->has('asistencia.calendarios', 12)
        );
});

it('honours the requested year on the asistencia page', function (): void {
    $conductor = Conductor::factory()->create();

    actingAs(actorConRol('admin'))
        ->get(route('conductores.asistencia', [$conductor, 'anio' => 2025]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('asistencia.anio', 2025)
            ->where('asistencia.calendarios.0.mes', '2025-01-01')
        );
});

it('keeps the asistencia page away from a visor', function (): void {
    $conductor = Conductor::factory()->create();

    actingAs(actorConRol('visor'))
        ->get(route('conductores.asistencia', $conductor))
        ->assertForbidden();
});
