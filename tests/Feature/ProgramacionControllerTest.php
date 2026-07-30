<?php

use App\Enums\TipoCarga;
use App\Enums\TipoNovedad;
use App\Models\Conductor;
use App\Models\EstadoUnidad;
use App\Models\Novedad;
use App\Models\Vehiculo;
use App\Services\MotorProgramacion;
use Database\Seeders\UbicacionSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    foreach (['admin', 'visor', 'conductor'] as $role) {
        Role::findOrCreate($role, 'web');
    }

    $this->seed(UbicacionSeeder::class);
});

/**
 * Una unidad lista para subir, en la fecha indicada.
 */
function lista(string $placa, string $fecha): EstadoUnidad
{
    return EstadoUnidad::factory()->en('juliaca')->create([
        'tracto_id' => Vehiculo::factory()->create(['placa' => $placa])->id,
        'conductor_id' => Conductor::factory()->create()->id,
        'tipo_carga' => TipoCarga::Vacio,
        'fecha' => $fecha,
    ]);
}

it('exige sesión para ver la programación', function (): void {
    $this->get(route('programacion.index'))->assertRedirect(route('login'));
});

it('no deja entrar a un conductor', function (): void {
    actingAs(actorConRol('conductor'))
        ->get(route('programacion.index'))
        ->assertForbidden();
});

it('propone la programación del día con los cupos pedidos', function (): void {
    lista('AAA-111', '2026-07-21');
    lista('BBB-222', '2026-07-21');

    actingAs(actorConRol('admin'))
        ->get(route('programacion.index', ['fecha' => '2026-07-21', 'cupos' => 1]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('programacion/index')
            ->where('cupos', 1)
            ->has('resultado.titulares', 1)
            ->has('resultado.reservas', 1)
            ->where('resultado.titulares.0.numero', 1)
            ->where('resultado.titulares.0.hora', MotorProgramacion::HORAS[0])
            ->where('resultado.titulares.0.empresa', MotorProgramacion::EMPRESA)
        );
});

it('arma la tabla con las cargadas primero y sin numerar', function (): void {
    lista('AAA-111', '2026-07-21');
    EstadoUnidad::factory()->conCarga(TipoCarga::Escoria)->en('azangaro')
        ->create(['fecha' => '2026-07-21']);

    actingAs(actorConRol('admin'))
        ->get(route('programacion.index', ['fecha' => '2026-07-21', 'cupos' => 5]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('resultado.tabla', 2)
            ->where('resultado.tabla.0.numero', null)
            ->where('resultado.tabla.0.observaciones', 'Inicio de tránsito desde Azángaro')
            ->where('resultado.tabla.1.numero', 1)
        );
});

it('avisa de los cupos que quedan sin cubrir', function (): void {
    lista('AAA-111', '2026-07-21');

    actingAs(actorConRol('admin'))
        ->get(route('programacion.index', ['fecha' => '2026-07-21', 'cupos' => 4]))
        ->assertInertia(fn (Assert $page) => $page->where('resultado.cupos_libres', 3));
});

it('lista las no programables con su motivo', function (): void {
    EstadoUnidad::factory()->en('juliaca')->sinConductor()->create([
        'tipo_carga' => TipoCarga::Vacio,
        'fecha' => '2026-07-21',
    ]);

    actingAs(actorConRol('admin'))
        ->get(route('programacion.index', ['fecha' => '2026-07-21']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('resultado.no_programables', 1)
            ->where('resultado.no_programables.0.motivo', 'Sin conductor asignado')
        );
});

it('cae en cupos por defecto y en hoy cuando no se pide nada', function (): void {
    actingAs(actorConRol('admin'))
        ->get(route('programacion.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('fecha', now()->toDateString())
            ->where('cupos', 10)
        );
});

it('acota los cupos disparatados', function (): void {
    actingAs(actorConRol('admin'))
        ->get(route('programacion.index', ['cupos' => 99999]))
        ->assertInertia(fn (Assert $page) => $page->where('cupos', 200));
});

it('admite cero cupos sin romperse', function (): void {
    lista('AAA-111', '2026-07-21');

    actingAs(actorConRol('admin'))
        ->get(route('programacion.index', ['fecha' => '2026-07-21', 'cupos' => 0]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('cupos', 0)
            ->has('resultado.titulares', 0)
            ->has('resultado.reservas', 1)
        );
});

it('registra una novedad y saca a la unidad de la programación', function (): void {
    $estado = lista('AAA-111', '2026-07-21');

    actingAs(actorConRol('admin'))
        ->post(route('novedades.store'), [
            'tracto_id' => $estado->tracto_id,
            'tipo' => TipoNovedad::NoHabido->value,
            'desde' => '2026-07-21',
        ])
        ->assertRedirect();

    actingAs(actorConRol('admin'))
        ->get(route('programacion.index', ['fecha' => '2026-07-21', 'cupos' => 5]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('resultado.titulares', 0)
            ->where('resultado.no_programables.0.motivo', 'No habido')
            ->has('novedades', 1)
        );
});

it('levanta una novedad sin borrarla y devuelve la unidad al ruedo', function (): void {
    $estado = lista('AAA-111', '2026-07-21');

    $novedad = Novedad::factory()->create([
        'tracto_id' => $estado->tracto_id,
        'desde' => now()->subDay()->toDateString(),
    ]);

    actingAs(actorConRol('admin'))
        ->post(route('novedades.levantar', $novedad))
        ->assertRedirect();

    expect($novedad->fresh()->estaVigente())->toBeFalse()
        ->and(Novedad::query()->count())->toBe(1);

    actingAs(actorConRol('admin'))
        ->get(route('programacion.index', ['fecha' => now()->toDateString(), 'cupos' => 5]))
        ->assertInertia(fn (Assert $page) => $page->has('resultado.titulares', 1));
});

it('no admite una novedad sobre un vehículo que no es tracto', function (): void {
    $carreta = Vehiculo::factory()->carreta()->create();

    actingAs(actorConRol('admin'))
        ->post(route('novedades.store'), [
            'tracto_id' => $carreta->id,
            'tipo' => TipoNovedad::NoHabido->value,
            'desde' => '2026-07-21',
        ])
        ->assertSessionHasErrors('tracto_id');
});

it('deja al visor consultar pero no registrar novedades', function (): void {
    $estado = lista('AAA-111', '2026-07-21');
    $novedad = Novedad::factory()->create(['tracto_id' => $estado->tracto_id]);
    $visor = actorConRol('visor');

    actingAs($visor)->get(route('programacion.index'))->assertSuccessful();
    actingAs($visor)->post(route('novedades.store'), [
        'tracto_id' => $estado->tracto_id,
        'tipo' => TipoNovedad::NoHabido->value,
        'desde' => '2026-07-21',
    ])->assertForbidden();
    actingAs($visor)->post(route('novedades.levantar', $novedad))->assertForbidden();
});
