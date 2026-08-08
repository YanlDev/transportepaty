<?php

use App\Enums\EstadoAsistencia;
use App\Models\Asistencia;
use App\Models\Conductor;
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

it('lets a visor see the roster but not mark it', function (): void {
    $conductor = Conductor::factory()->create();

    actingAs(actorConRol('visor'))
        ->get(route('asistencia.index'))
        ->assertSuccessful();

    actingAs(actorConRol('visor'))
        ->patch(route('asistencia.marcar', $conductor), [
            'fecha' => now()->toDateString(),
            'estado' => EstadoAsistencia::Asistencia->value,
        ])
        ->assertForbidden();
});

it('builds a 30-day cycle starting on the 28th, not a calendar month', function (): void {
    $marcado = Conductor::factory()->create(['nombres' => 'Ana', 'apellidos' => 'Alarcon']);
    $sinMarcar = Conductor::factory()->create(['nombres' => 'Beto', 'apellidos' => 'Zeta']);
    Conductor::factory()->inactivo()->create();

    $asistencia = Asistencia::create([
        'conductor_id' => $marcado->id,
        'fecha' => '2026-02-10',
        'estado' => EstadoAsistencia::Falta,
    ]);

    // Un día después de que cierra el ciclo (28 ene - 26 feb): no debe
    // aparecer en esta grilla.
    Asistencia::create([
        'conductor_id' => $marcado->id,
        'fecha' => '2026-02-27',
        'estado' => EstadoAsistencia::Vacaciones,
    ]);

    actingAs(actorConRol('admin'))
        ->get(route('asistencia.index', ['inicio' => '2026-01-28']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('inicioCiclo', '2026-01-28')
            ->has('dias', 30)
            ->where('dias.0.fecha', '2026-01-28')
            ->where('dias.29.fecha', '2026-02-26')
            ->has('filas', 2)
            ->where('filas.0.conductor_id', $marcado->id)
            ->where('filas.0.marcas.2026-02-10.estado', 'falta')
            ->where('filas.0.marcas.2026-02-10.asistencia_id', $asistencia->id)
            ->missing('filas.0.marcas.2026-02-27')
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
