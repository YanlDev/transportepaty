<?php

use App\Models\Cliente;
use App\Models\Conductor;
use App\Models\Programacion;
use App\Models\Vehiculo;
use App\Models\Viaje;
use App\Services\RelojOperativo;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    foreach (['admin', 'visor', 'conductor', 'contador'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

/**
 * Los cuatro campos de una programación, ya persistidos, listos para mandar
 * en un POST o un PUT.
 *
 * @return array<string, mixed>
 */
function datosDeProgramacion(array $extra = []): array
{
    return array_merge([
        'fecha' => '2026-09-15',
        'vehiculo_id' => Vehiculo::factory()->create()->id,
        'conductor_id' => Conductor::factory()->create()->id,
        'cliente_id' => Cliente::factory()->create()->id,
        'destino' => 'JULIACA',
    ], $extra);
}

it('redirects guests to login', function (): void {
    $this->get(route('programacion.index'))->assertRedirect(route('login'));
});

it('lets the admin and the visor read it, and keeps the rest out', function (): void {
    // Abastecimiento lee con rol visor: necesita ver el plan del día, no armarlo.
    actingAs(actorConRol('admin'))->get(route('programacion.index'))->assertSuccessful();
    actingAs(actorConRol('visor'))->get(route('programacion.index'))->assertSuccessful();

    actingAs(actorConRol('contador'))->get(route('programacion.index'))->assertForbidden();
    actingAs(actorConRol('conductor'))->get(route('programacion.index'))->assertForbidden();
});

it('only lets the admin program, edit and remove', function (): void {
    $programacion = Programacion::factory()->create();

    actingAs(actorConRol('visor'))
        ->post(route('programacion.store'), datosDeProgramacion())
        ->assertForbidden();

    actingAs(actorConRol('visor'))
        ->put(route('programacion.update', $programacion), datosDeProgramacion())
        ->assertForbidden();

    actingAs(actorConRol('visor'))
        ->delete(route('programacion.destroy', $programacion))
        ->assertForbidden();

    expect(Programacion::query()->count())->toBe(1);
});

it('shows today when no date is given', function (): void {
    $hoy = RelojOperativo::fechaDeHoy()->toDateString();

    Programacion::factory()->elDia($hoy)->create();
    Programacion::factory()->elDia('2020-01-01')->create();

    actingAs(actorConRol('admin'))
        ->get(route('programacion.index'))
        ->assertInertia(fn ($page) => $page
            ->where('fecha', $hoy)
            ->has('programaciones', 1)
        );
});

it('falls back to today when the date in the URL is garbage', function (): void {
    // Llega por query string: cualquier texto tiene que dar el día de hoy, no un 500.
    actingAs(actorConRol('admin'))
        ->get(route('programacion.index', ['fecha' => 'no-es-una-fecha']))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('fecha', RelojOperativo::fechaDeHoy()->toDateString())
        );
});

it('only brings the units programmed for the day it is showing', function (): void {
    Programacion::factory()->elDia('2026-09-15')->count(3)->create();
    Programacion::factory()->elDia('2026-09-16')->create();

    actingAs(actorConRol('admin'))
        ->get(route('programacion.index', ['fecha' => '2026-09-15']))
        ->assertInertia(fn ($page) => $page->has('programaciones', 3));
});

it('groups the cards by client so the same client reads together', function (): void {
    $zeta = Cliente::factory()->create(['alias' => 'Zeta']);
    $alfa = Cliente::factory()->create(['alias' => 'Alfa']);

    Programacion::factory()->elDia('2026-09-15')->create(['cliente_id' => $zeta->id]);
    Programacion::factory()->elDia('2026-09-15')->create(['cliente_id' => $alfa->id]);
    Programacion::factory()->elDia('2026-09-15')->create(['cliente_id' => $zeta->id]);

    actingAs(actorConRol('admin'))
        ->get(route('programacion.index', ['fecha' => '2026-09-15']))
        ->assertInertia(fn ($page) => $page
            ->where('programaciones.0.cliente', 'Alfa')
            ->where('programaciones.1.cliente', 'Zeta')
            ->where('programaciones.2.cliente', 'Zeta')
        );
});

it('counts the programmed units of each day of the week', function (): void {
    // 2026-09-15 es martes; la semana va del lunes 14 al domingo 20.
    Programacion::factory()->elDia('2026-09-14')->count(2)->create();
    Programacion::factory()->elDia('2026-09-15')->create();
    // Fuera de la semana: no debe contarse en ningún día de la tira.
    Programacion::factory()->elDia('2026-09-21')->create();

    actingAs(actorConRol('admin'))
        ->get(route('programacion.index', ['fecha' => '2026-09-15']))
        ->assertInertia(fn ($page) => $page
            ->has('semana', 7)
            ->where('semana.0.fecha', '2026-09-14')
            ->where('semana.0.programadas', 2)
            ->where('semana.1.programadas', 1)
            ->where('semana.2.programadas', 0)
            ->where('semana.6.fecha', '2026-09-20')
        );
});

it('programs a unit', function (): void {
    $datos = datosDeProgramacion();

    actingAs(actorConRol('admin'))
        ->post(route('programacion.store'), $datos)
        ->assertRedirect();

    $this->assertDatabaseHas('programaciones', [
        'fecha' => '2026-09-15',
        'vehiculo_id' => $datos['vehiculo_id'],
        'destino' => 'JULIACA',
    ]);
});

it('refuses a carreta as the unit: particular cargo moves on the tracto', function (): void {
    $carreta = Vehiculo::factory()->carreta()->create();

    actingAs(actorConRol('admin'))
        ->post(route('programacion.store'), datosDeProgramacion(['vehiculo_id' => $carreta->id]))
        ->assertSessionHasErrors('vehiculo_id');

    expect(Programacion::query()->count())->toBe(0);
});

it('requires every field', function (): void {
    actingAs(actorConRol('admin'))
        ->post(route('programacion.store'), [])
        ->assertSessionHasErrors(['fecha', 'vehiculo_id', 'conductor_id', 'cliente_id', 'destino']);
});

it('corrects a programmed unit', function (): void {
    $programacion = Programacion::factory()->create(['destino' => 'JULIACA']);
    $otroConductor = Conductor::factory()->create();

    actingAs(actorConRol('admin'))
        ->put(route('programacion.update', $programacion), datosDeProgramacion([
            'vehiculo_id' => $programacion->vehiculo_id,
            'conductor_id' => $otroConductor->id,
            'cliente_id' => $programacion->cliente_id,
            'destino' => 'ILAVE',
        ]))
        ->assertRedirect();

    expect($programacion->refresh()->destino)->toBe('ILAVE')
        ->and($programacion->conductor_id)->toBe($otroConductor->id);
});

it('removes a programmed unit', function (): void {
    $programacion = Programacion::factory()->create();

    actingAs(actorConRol('admin'))
        ->delete(route('programacion.destroy', $programacion))
        ->assertRedirect();

    $this->assertDatabaseMissing('programaciones', ['id' => $programacion->id]);
});

it('only offers active tractos and active conductores to program', function (): void {
    Vehiculo::factory()->create(['placa' => 'AAA111']);
    Vehiculo::factory()->carreta()->create();
    Vehiculo::factory()->enMantenimiento()->create();

    Conductor::factory()->create(['activo' => true]);
    Conductor::factory()->create(['activo' => false]);

    actingAs(actorConRol('admin'))
        ->get(route('programacion.index'))
        ->assertInertia(fn ($page) => $page
            ->has('unidades', 1)
            ->where('unidades.0.placa', 'AAA111')
            ->has('conductores', 1)
        );
});

it('offers the destinations already typed, so the same place is not written twice', function (): void {
    Programacion::factory()->create(['destino' => 'ILAVE']);
    Programacion::factory()->create(['destino' => 'ILAVE']);
    Programacion::factory()->create(['destino' => 'JULIACA']);

    actingAs(actorConRol('admin'))
        ->get(route('programacion.index'))
        ->assertInertia(fn ($page) => $page
            ->has('destinosUsados', 2)
            ->where('destinosUsados.0', 'ILAVE')
            ->where('destinosUsados.1', 'JULIACA')
        );
});

/**
 * Lo que hace rápida la carga: elegir el conductor deja puesta la unidad con
 * la que salió la última vez, porque un chofer maneja casi siempre el mismo
 * tracto. El dato sale de las guías, no de programaciones anteriores.
 */
it('sends the unit of each conductor last GR, to prefill the form', function (): void {
    $conductor = Conductor::factory()->create();
    $viejo = Vehiculo::factory()->create(['placa' => 'VIEJO11']);
    $reciente = Vehiculo::factory()->create(['placa' => 'NUEVO22']);

    Viaje::factory()->create([
        'conductor_id' => $conductor->id,
        'tracto_id' => $viejo->id,
        'fecha_traslado' => '2026-08-01',
    ]);

    Viaje::factory()->create([
        'conductor_id' => $conductor->id,
        'tracto_id' => $reciente->id,
        'fecha_traslado' => '2026-09-05',
    ]);

    actingAs(actorConRol('admin'))
        ->get(route('programacion.index'))
        ->assertInertia(fn ($page) => $page
            ->where("ultimoViajePorConductor.{$conductor->id}.vehiculo_id", $reciente->id)
            ->where("ultimoViajePorConductor.{$conductor->id}.placa", 'NUEVO22')
            ->where("ultimoViajePorConductor.{$conductor->id}.fecha", '2026-09-05')
        );
});

it('leaves out conductores with no GR to prefill from', function (): void {
    // Un chofer nuevo no tiene con qué prellenar: mejor nada que una unidad
    // inventada.
    $sinViajes = Conductor::factory()->create();

    actingAs(actorConRol('admin'))
        ->get(route('programacion.index'))
        ->assertInertia(fn ($page) => $page
            ->missing("ultimoViajePorConductor.{$sinViajes->id}")
        );
});

it('ignores trips whose tracto could not be resolved', function (): void {
    $conductor = Conductor::factory()->create();
    $tracto = Vehiculo::factory()->create(['placa' => 'SIRVE11']);

    // La GR más reciente quedó sin tracto resuelto: debe caer a la anterior
    // que sí lo tiene, en vez de dejar al conductor sin prellenado.
    Viaje::factory()->create([
        'conductor_id' => $conductor->id,
        'tracto_id' => null,
        'fecha_traslado' => '2026-09-10',
    ]);

    Viaje::factory()->create([
        'conductor_id' => $conductor->id,
        'tracto_id' => $tracto->id,
        'fecha_traslado' => '2026-09-01',
    ]);

    actingAs(actorConRol('admin'))
        ->get(route('programacion.index'))
        ->assertInertia(fn ($page) => $page
            ->where("ultimoViajePorConductor.{$conductor->id}.placa", 'SIRVE11')
        );
});

it('programs for a day other than the one being shown', function (): void {
    // El día de carga es un campo del formulario: se programa mirando hoy
    // para cargar mañana.
    actingAs(actorConRol('admin'))
        ->get(route('programacion.index', ['fecha' => '2026-09-15']));

    actingAs(actorConRol('admin'))
        ->post(route('programacion.store'), datosDeProgramacion(['fecha' => '2026-09-20']))
        ->assertRedirect();

    $this->assertDatabaseHas('programaciones', ['fecha' => '2026-09-20']);
});

it('sends each client RUC, which is how the express form finds the one it just created', function (): void {
    Cliente::factory()->create(['alias' => 'Minsur', 'ruc' => '20100136741', 'activo' => true]);
    Cliente::factory()->create(['activo' => false]);

    actingAs(actorConRol('admin'))
        ->get(route('programacion.index'))
        ->assertInertia(fn ($page) => $page
            ->has('clientes', 1)
            ->where('clientes.0.alias', 'Minsur')
            ->where('clientes.0.ruc', '20100136741')
        );
});
