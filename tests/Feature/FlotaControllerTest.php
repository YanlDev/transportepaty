<?php

use App\Enums\TipoCarga;
use App\Models\EstadoUnidad;
use App\Models\Ubicacion;
use App\Models\Vehiculo;
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

it('exige sesión para ver la flota', function (): void {
    $this->get(route('flota.index'))->assertRedirect(route('login'));
});

it('no deja entrar a un conductor', function (): void {
    actingAs(actorConRol('conductor'))
        ->get(route('flota.index'))
        ->assertForbidden();
});

it('muestra el último estado conocido aunque el día de hoy no se haya cargado', function (): void {
    $tracto = Vehiculo::factory()->create(['placa' => 'VEP-856']);

    EstadoUnidad::factory()->conCarga(TipoCarga::Concentrado)->en('camana')
        ->create(['tracto_id' => $tracto->id, 'fecha' => now()->subDays(9)->toDateString()]);
    EstadoUnidad::factory()->conCarga(TipoCarga::Concentrado)->en('nazca')
        ->create(['tracto_id' => $tracto->id, 'fecha' => now()->subDays(2)->toDateString()]);

    actingAs(actorConRol('admin'))
        ->get(route('flota.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('flota/index')
            ->where('resumen.unidades', 1)
            ->has('puntos', 1)
            // El punto que manda es el del reporte más reciente.
            ->where('puntos.0.nombre', 'Nazca')
            ->where('puntos.0.unidades.0.placa', 'VEP-856')
        );
});

it('agrupa varias unidades bajo un mismo punto del mapa', function (): void {
    EstadoUnidad::factory()->count(3)->en('juliaca')->create(['fecha' => now()->toDateString()]);

    actingAs(actorConRol('admin'))
        ->get(route('flota.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('puntos', 1)
            ->where('puntos.0.total', 3)
            ->where('puntos.0.es_zona_base', true)
        );
});

it('deja fuera del mapa los puntos sin coordenadas confirmadas', function (): void {
    // Ransa se siembra sin posición a propósito, hasta que alguien la marque.
    EstadoUnidad::factory()->en('ransa')->create(['fecha' => now()->toDateString()]);

    actingAs(actorConRol('admin'))
        ->get(route('flota.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('puntos', 0)
            ->where('resumen.sin_posicion', 1)
        );
});

it('agrupa las próximas descargas por destino y ordena por llegada', function (): void {
    // Nazca está más cerca de Pisco que Camaná, así que llega antes.
    EstadoUnidad::factory()->conCarga(TipoCarga::Concentrado)->en('camana')
        ->create(['fecha' => now()->toDateString()]);
    EstadoUnidad::factory()->conCarga(TipoCarga::Concentrado)->en('nazca')
        ->create(['fecha' => now()->toDateString()]);

    actingAs(actorConRol('admin'))
        ->get(route('flota.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('descargas', 1)
            ->where('descargas.0.destino', 'Pisco')
            ->where('descargas.0.total', 2)
            ->where('descargas.0.unidades.0.ubicacion', 'Nazca')
            ->where('descargas.0.unidades.1.ubicacion', 'Camaná')
        );
});

it('avisa cuando la estimación todavía no está calibrada', function (): void {
    EstadoUnidad::factory()->conCarga(TipoCarga::Concentrado)->en('nazca')
        ->create(['fecha' => now()->toDateString()]);

    actingAs(actorConRol('admin'))
        ->get(route('flota.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('resumen.estimacion_calibrada', false)
            ->where('descargas.0.unidades.0.estimacion.calibrada', false)
        );
});

it('no lista como descarga a la unidad cuyo destino queda fuera del corredor', function (): void {
    EstadoUnidad::factory()->en('juliaca')->create([
        'fecha' => now()->toDateString(),
        'destino_id' => Ubicacion::query()->where('codigo', 'cusco')->value('id'),
    ]);

    actingAs(actorConRol('admin'))
        ->get(route('flota.index'))
        ->assertInertia(fn (Assert $page) => $page->has('descargas', 0));
});

it('muestra la línea de tiempo de una unidad, del día más reciente al más antiguo', function (): void {
    $tracto = Vehiculo::factory()->create(['placa' => 'VEP-856']);

    EstadoUnidad::factory()->en('juliaca')->create([
        'tracto_id' => $tracto->id, 'fecha' => '2026-07-18',
    ]);
    EstadoUnidad::factory()->en('nazca')->create([
        'tracto_id' => $tracto->id, 'fecha' => '2026-07-21',
    ]);

    actingAs(actorConRol('admin'))
        ->get(route('flota.unidad', $tracto))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('flota/unidad')
            ->where('tracto.placa', 'VEP-856')
            ->has('linea', 2)
            ->where('linea.0.fecha', '2026-07-21')
            ->where('linea.0.ubicacion', 'Nazca')
            ->where('linea.1.fecha', '2026-07-18')
        );
});

it('muestra en la línea de tiempo el texto crudo de la ubicación sin resolver', function (): void {
    $tracto = Vehiculo::factory()->create();

    EstadoUnidad::factory()->conUbicacionSinResolver('Grifo km 48')->create([
        'tracto_id' => $tracto->id, 'fecha' => '2026-07-21',
    ]);

    actingAs(actorConRol('admin'))
        ->get(route('flota.unidad', $tracto))
        ->assertInertia(fn (Assert $page) => $page->where('linea.0.ubicacion', 'Grifo km 48'));
});

it('deja al visor consultar la flota', function (): void {
    actingAs(actorConRol('visor'))->get(route('flota.index'))->assertSuccessful();
});
