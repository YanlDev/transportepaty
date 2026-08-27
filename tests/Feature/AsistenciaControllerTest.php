<?php

use App\Enums\EstadoAsistencia;
use App\Models\Asistencia;
use App\Models\Conductor;
use App\Models\DescansoDebido;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    foreach (['admin', 'visor', 'conductor'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

it('redirects guests to login', function (): void {
    $this->get(route('asistencia.index'))->assertRedirect(route('login'));
});

it('forbids a visor from seeing the roster or marking it', function (): void {
    $conductor = Conductor::factory()->create();

    actingAs(actorConRol('visor'))
        ->get(route('asistencia.index'))
        ->assertForbidden();

    actingAs(actorConRol('visor'))
        ->patch(route('asistencia.marcar', $conductor), [
            'fecha' => now()->toDateString(),
            'estado' => EstadoAsistencia::Asistencia->value,
        ])
        ->assertForbidden();
});

it('builds a cycle starting on the 28th that runs through the day before the next 28th', function (): void {
    $marcado = Conductor::factory()->create(['nombres' => 'Ana', 'apellidos' => 'Alarcon']);
    $sinMarcar = Conductor::factory()->create(['nombres' => 'Beto', 'apellidos' => 'Zeta']);
    Conductor::factory()->inactivo()->create();

    $asistencia = Asistencia::create([
        'conductor_id' => $marcado->id,
        'fecha' => '2026-02-10',
        'estado' => EstadoAsistencia::Falta,
    ]);

    // Un día después de que cierra el ciclo (28 ene - 27 feb, enero tiene
    // 31 días): no debe aparecer en esta grilla.
    Asistencia::create([
        'conductor_id' => $marcado->id,
        'fecha' => '2026-02-28',
        'estado' => EstadoAsistencia::Vacaciones,
    ]);

    actingAs(actorConRol('admin'))
        ->get(route('asistencia.index', ['inicio' => '2026-01-28']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('inicioCiclo', '2026-01-28')
            ->has('dias', 31)
            ->where('dias.0.fecha', '2026-01-28')
            ->where('dias.30.fecha', '2026-02-27')
            ->has('filas', 2)
            ->where('filas.0.conductor_id', $marcado->id)
            ->where('filas.0.marcas.2026-02-10.estado', 'falta')
            ->where('filas.0.marcas.2026-02-10.asistencia_id', $asistencia->id)
            ->missing('filas.0.marcas.2026-02-28')
            ->where('filas.1.conductor_id', $sinMarcar->id)
            ->where('filas.1.marcas', [])
        );
});

it('defaults to the cycle in progress today when no cycle is requested', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-02-27'));

    actingAs(actorConRol('admin'))
        ->get(route('asistencia.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->where('inicioCiclo', '2026-01-28'));

    $this->travelTo(CarbonImmutable::parse('2026-02-28'));

    actingAs(actorConRol('admin'))
        ->get(route('asistencia.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->where('inicioCiclo', '2026-02-28'));
});

it('orders conductores alphabetically by apellidos, matching the paper rooster', function (): void {
    Conductor::factory()->create(['nombres' => 'Carlos', 'apellidos' => 'Zapata']);
    Conductor::factory()->create(['nombres' => 'Ana', 'apellidos' => 'Alarcon']);
    Conductor::factory()->create(['nombres' => 'Beto', 'apellidos' => 'Mamani']);

    actingAs(actorConRol('admin'))
        ->get(route('asistencia.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filas.0.nombre_completo', 'Alarcon Ana')
            ->where('filas.1.nombre_completo', 'Mamani Beto')
            ->where('filas.2.nombre_completo', 'Zapata Carlos')
        );
});

it('lets an admin mark a conductor and change the mark afterwards', function (): void {
    $conductor = Conductor::factory()->create();

    actingAs(actorConRol('admin'))
        ->patch(route('asistencia.marcar', $conductor), [
            'fecha' => '2026-08-03',
            'estado' => EstadoAsistencia::Descanso->value,
        ])
        ->assertSessionHasNoErrors();

    expect(Asistencia::query()->count())->toBe(1);
    expect(Asistencia::query()->sole()->estado)->toBe(EstadoAsistencia::Descanso);

    actingAs(actorConRol('admin'))
        ->patch(route('asistencia.marcar', $conductor), [
            'fecha' => '2026-08-03',
            'estado' => EstadoAsistencia::Vacaciones->value,
        ])
        ->assertSessionHasNoErrors();

    expect(Asistencia::query()->count())->toBe(1)
        ->and(Asistencia::query()->sole()->estado)->toBe(EstadoAsistencia::Vacaciones);
});

it('lets an admin clear a mark back to unmarked', function (): void {
    $conductor = Conductor::factory()->create();
    $asistencia = Asistencia::create([
        'conductor_id' => $conductor->id,
        'fecha' => '2026-08-03',
        'estado' => EstadoAsistencia::Falta,
    ]);

    actingAs(actorConRol('admin'))
        ->delete(route('asistencia.destroy', $asistencia))
        ->assertSessionHasNoErrors();

    expect(Asistencia::query()->count())->toBe(0);
});

it('rejects an invalid estado', function (): void {
    $conductor = Conductor::factory()->create();

    actingAs(actorConRol('admin'))
        ->patch(route('asistencia.marcar', $conductor), [
            'fecha' => '2026-08-03',
            'estado' => 'inventado',
        ])
        ->assertSessionHasErrors('estado');
});

it('forbids a visor from seeing the individual calendar', function (): void {
    $conductor = Conductor::factory()->create();

    actingAs(actorConRol('visor'))
        ->get(route('asistencia.show', $conductor))
        ->assertForbidden();
});

it('defaults the individual calendar to the current month and 2 months at a time', function (): void {
    $conductor = Conductor::factory()->create();

    $this->travelTo(CarbonImmutable::parse('2026-08-15'));

    actingAs(actorConRol('admin'))
        ->get(route('asistencia.show', $conductor))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('mes', '2026-08-01')
            ->where('cantidadMeses', 2)
            ->has('calendarios', 2)
            ->where('calendarios.0.mes', '2026-08-01')
            ->where('calendarios.1.mes', '2026-09-01')
        );
});

it('builds a full-week grid for the requested month, padding with neighboring days', function (): void {
    $conductor = Conductor::factory()->create();

    actingAs(actorConRol('admin'))
        ->get(route('asistencia.show', [$conductor, 'mes' => '2026-08-01', 'meses' => 1]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('conductor.id', $conductor->id)
            ->where('conductor.nombre_completo', "{$conductor->apellidos} {$conductor->nombres}")
            ->has('calendarios.0.dias', 42)
            // Agosto 2026 empieza en sábado: la grilla arranca el lunes
            // anterior (27 de julio) para completar la semana.
            ->where('calendarios.0.dias.0.fecha', '2026-07-27')
            ->where('calendarios.0.dias.0.es_relleno', true)
            ->where('calendarios.0.dias.5.fecha', '2026-08-01')
            ->where('calendarios.0.dias.5.es_relleno', false)
            ->where('calendarios.0.dias.35.fecha', '2026-08-31')
            ->where('calendarios.0.dias.35.es_relleno', false)
            // Agosto termina en lunes: la grilla sigue hasta el domingo
            // siguiente (6 de setiembre) para cerrar esa semana.
            ->where('calendarios.0.dias.41.fecha', '2026-09-06')
            ->where('calendarios.0.dias.41.es_relleno', true)
        );
});

it('clamps the requested amount of months between 1 and 4', function (): void {
    $conductor = Conductor::factory()->create();

    actingAs(actorConRol('admin'))
        ->get(route('asistencia.show', [$conductor, 'meses' => 10]))
        ->assertInertia(fn (Assert $page) => $page->where('cantidadMeses', 4));

    actingAs(actorConRol('admin'))
        ->get(route('asistencia.show', [$conductor, 'meses' => 0]))
        ->assertInertia(fn (Assert $page) => $page->where('cantidadMeses', 1));
});

it('only brings marks for the requested conductor within each month of the calendar', function (): void {
    $conductor = Conductor::factory()->create();
    $otro = Conductor::factory()->create();

    $asistencia = Asistencia::create([
        'conductor_id' => $conductor->id,
        'fecha' => '2026-08-10',
        'estado' => EstadoAsistencia::Vacaciones,
    ]);

    // Fuera del mes pedido y de otro conductor: ninguna debe aparecer.
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
        ->get(route('asistencia.show', [$conductor, 'mes' => '2026-08-01', 'meses' => 1]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('calendarios.0.marcas.2026-08-10.estado', 'vacaciones')
            ->where('calendarios.0.marcas.2026-08-10.asistencia_id', $asistencia->id)
            ->missing('calendarios.0.marcas.2026-07-30')
            ->has('calendarios.0.marcas', 1)
        );
});

it('reflects a mark and a removal made from the individual calendar in the roster data', function (): void {
    $conductor = Conductor::factory()->create();

    actingAs(actorConRol('admin'))
        ->patch(route('asistencia.marcar', $conductor), [
            'fecha' => '2026-08-10',
            'estado' => EstadoAsistencia::Asistencia->value,
        ])
        ->assertSessionHasNoErrors();

    $asistencia = Asistencia::query()->sole();

    actingAs(actorConRol('admin'))
        ->get(route('asistencia.show', [$conductor, 'mes' => '2026-08-01', 'meses' => 1]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('calendarios.0.marcas.2026-08-10.asistencia_id', $asistencia->id)
            ->where('calendarios.0.marcas.2026-08-10.estado', 'asistencia')
        );

    actingAs(actorConRol('admin'))
        ->delete(route('asistencia.destroy', $asistencia))
        ->assertSessionHasNoErrors();

    actingAs(actorConRol('admin'))
        ->get(route('asistencia.show', [$conductor, 'mes' => '2026-08-01', 'meses' => 1]))
        ->assertInertia(fn (Assert $page) => $page->missing('calendarios.0.marcas.2026-08-10'));
});

it('returns a 404 for a conductor that does not exist', function (): void {
    actingAs(actorConRol('admin'))
        ->get(route('asistencia.show', ['conductor' => 999999]))
        ->assertNotFound();
});

it('lets an admin set the días debidos for a conductor in a given month', function (): void {
    $conductor = Conductor::factory()->create();

    actingAs(actorConRol('admin'))
        ->patch(route('asistencia.diasDebidos', $conductor), [
            'mes' => '2026-08-15',
            'dias_debidos' => 3,
        ])
        ->assertSessionHasNoErrors();

    expect(DescansoDebido::query()->count())->toBe(1);

    $descansoDebido = DescansoDebido::query()->sole();
    expect($descansoDebido->conductor_id)->toBe($conductor->id)
        ->and($descansoDebido->mes->toDateString())->toBe('2026-08-01')
        ->and($descansoDebido->dias_debidos)->toBe(3);

    actingAs(actorConRol('admin'))
        ->get(route('asistencia.show', [$conductor, 'mes' => '2026-08-01', 'meses' => 1]))
        ->assertInertia(fn (Assert $page) => $page->where('calendarios.0.dias_debidos', 3));
});

it('lets an admin change the días debidos for a month without touching other months', function (): void {
    $conductor = Conductor::factory()->create();

    actingAs(actorConRol('admin'))
        ->patch(route('asistencia.diasDebidos', $conductor), [
            'mes' => '2026-08-01',
            'dias_debidos' => 4,
        ])
        ->assertSessionHasNoErrors();

    actingAs(actorConRol('admin'))
        ->patch(route('asistencia.diasDebidos', $conductor), [
            'mes' => '2026-08-01',
            'dias_debidos' => 2,
        ])
        ->assertSessionHasNoErrors();

    actingAs(actorConRol('admin'))
        ->patch(route('asistencia.diasDebidos', $conductor), [
            'mes' => '2026-09-01',
            'dias_debidos' => 6,
        ])
        ->assertSessionHasNoErrors();

    expect(DescansoDebido::query()->count())->toBe(2);

    actingAs(actorConRol('admin'))
        ->get(route('asistencia.show', [$conductor, 'mes' => '2026-08-01', 'meses' => 2]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('calendarios.0.dias_debidos', 2)
            ->where('calendarios.1.dias_debidos', 6)
        );
});

it('defaults días debidos to 0 for a month that was never set', function (): void {
    $conductor = Conductor::factory()->create();

    actingAs(actorConRol('admin'))
        ->get(route('asistencia.show', [$conductor, 'mes' => '2026-08-01', 'meses' => 1]))
        ->assertInertia(fn (Assert $page) => $page->where('calendarios.0.dias_debidos', 0));
});

it('forbids a visor from setting días debidos', function (): void {
    $conductor = Conductor::factory()->create();

    actingAs(actorConRol('visor'))
        ->patch(route('asistencia.diasDebidos', $conductor), [
            'mes' => '2026-08-01',
            'dias_debidos' => 3,
        ])
        ->assertForbidden();
});

it('rejects an out-of-range días debidos', function (): void {
    $conductor = Conductor::factory()->create();

    actingAs(actorConRol('admin'))
        ->patch(route('asistencia.diasDebidos', $conductor), [
            'mes' => '2026-08-01',
            'dias_debidos' => 32,
        ])
        ->assertSessionHasErrors('dias_debidos');

    actingAs(actorConRol('admin'))
        ->patch(route('asistencia.diasDebidos', $conductor), [
            'mes' => '2026-08-01',
            'dias_debidos' => -32,
        ])
        ->assertSessionHasErrors('dias_debidos');
});

it('lets an admin record a negative saldo when the conductor rested more than expected', function (): void {
    $conductor = Conductor::factory()->create();

    actingAs(actorConRol('admin'))
        ->patch(route('asistencia.diasDebidos', $conductor), [
            'mes' => '2026-08-01',
            'dias_debidos' => -1,
        ])
        ->assertSessionHasNoErrors();

    // Negativo: descansó de más ese mes, así que le debe un día de trabajo
    // a la empresa en vez de que la empresa le deba un descanso a él.
    expect(DescansoDebido::query()->sole()->dias_debidos)->toBe(-1);

    actingAs(actorConRol('admin'))
        ->get(route('asistencia.show', [$conductor, 'mes' => '2026-08-01', 'meses' => 1]))
        ->assertInertia(fn (Assert $page) => $page->where('calendarios.0.dias_debidos', -1));
});

it('lets an admin set notas for a conductor in a given month', function (): void {
    $conductor = Conductor::factory()->create();

    actingAs(actorConRol('admin'))
        ->patch(route('asistencia.notas', $conductor), [
            'mes' => '2026-08-15',
            'notas' => 'Acordó reponer el 30/08 el día que faltó por trámite.',
        ])
        ->assertSessionHasNoErrors();

    $descansoDebido = DescansoDebido::query()->sole();
    expect($descansoDebido->conductor_id)->toBe($conductor->id)
        ->and($descansoDebido->mes->toDateString())->toBe('2026-08-01')
        ->and($descansoDebido->notas)->toBe('Acordó reponer el 30/08 el día que faltó por trámite.');

    actingAs(actorConRol('admin'))
        ->get(route('asistencia.show', [$conductor, 'mes' => '2026-08-01', 'meses' => 1]))
        ->assertInertia(fn (Assert $page) => $page->where(
            'calendarios.0.notas',
            'Acordó reponer el 30/08 el día que faltó por trámite.',
        ));
});

it('clears notas when saved empty without touching días debidos', function (): void {
    $conductor = Conductor::factory()->create();

    actingAs(actorConRol('admin'))
        ->patch(route('asistencia.diasDebidos', $conductor), [
            'mes' => '2026-08-01',
            'dias_debidos' => 2,
        ])
        ->assertSessionHasNoErrors();

    actingAs(actorConRol('admin'))
        ->patch(route('asistencia.notas', $conductor), [
            'mes' => '2026-08-01',
            'notas' => 'Nota provisional',
        ])
        ->assertSessionHasNoErrors();

    actingAs(actorConRol('admin'))
        ->patch(route('asistencia.notas', $conductor), [
            'mes' => '2026-08-01',
            'notas' => '',
        ])
        ->assertSessionHasNoErrors();

    $descansoDebido = DescansoDebido::query()->sole();
    expect($descansoDebido->notas)->toBeNull()
        ->and($descansoDebido->dias_debidos)->toBe(2);
});

it('defaults notas to null for a month that was never set', function (): void {
    $conductor = Conductor::factory()->create();

    actingAs(actorConRol('admin'))
        ->get(route('asistencia.show', [$conductor, 'mes' => '2026-08-01', 'meses' => 1]))
        ->assertInertia(fn (Assert $page) => $page->where('calendarios.0.notas', null));
});

it('forbids a visor from setting notas', function (): void {
    $conductor = Conductor::factory()->create();

    actingAs(actorConRol('visor'))
        ->patch(route('asistencia.notas', $conductor), [
            'mes' => '2026-08-01',
            'notas' => 'Intento no autorizado',
        ])
        ->assertForbidden();
});

it('rejects notas over the length limit', function (): void {
    $conductor = Conductor::factory()->create();

    actingAs(actorConRol('admin'))
        ->patch(route('asistencia.notas', $conductor), [
            'mes' => '2026-08-01',
            'notas' => str_repeat('a', 2001),
        ])
        ->assertSessionHasErrors('notas');
});
