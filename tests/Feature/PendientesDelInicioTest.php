<?php

use App\Enums\EstadoVuelta;
use App\Enums\OrigenVuelta;
use App\Enums\SentidoTramo;
use App\Enums\TipoCarga;
use App\Enums\TipoVehiculo;
use App\Enums\ZonaRuta;
use App\Models\ParadaVuelta;
use App\Models\RevisionTaller;
use App\Models\Vehiculo;
use App\Models\Viaje;
use App\Models\Vuelta;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

/**
 * El Inicio con lo que le toca a cada quien: taller ve lo que espera
 * revisión, abastecimiento lo que sale sin combustible, programación lo que
 * hay que programar o cerrar, facturación las GR sin facturar.
 */
beforeEach(function (): void {
    foreach (['admin', 'contador'] as $rol) {
        Role::findOrCreate($rol, 'web');
    }
});

/**
 * Un ticket programado de la mina a Pisco con concentrado.
 */
function ticketMinaPisco(array $atributos = []): Vuelta
{
    $vuelta = Vuelta::factory()->programada()->create([
        'tracto_id' => Vehiculo::factory()->create(['tipo' => TipoVehiculo::Tracto])->id,
        'inicio' => now()->toDateString(),
        ...$atributos,
    ]);
    ParadaVuelta::factory()->create([
        'vuelta_id' => $vuelta->id, 'sentido' => SentidoTramo::Ida, 'orden' => 1,
        'zona_origen' => ZonaRuta::Mina, 'zona_destino' => ZonaRuta::Pisco, 'tipo_carga' => TipoCarga::Concentrado,
        'lugar_origen' => null, 'lugar_destino' => null,
    ]);

    return $vuelta;
}

/**
 * @return array<string, array<string, mixed>>
 */
function pendientesDe(string $rol): array
{
    $pagina = actingAs(actorConRol($rol))->get(route('dashboard'))->assertOk();

    return collect($pagina->viewData('page')['props']['pendientes'])->keyBy('clave')->all();
}

it('shows the workshop what waits for its OK', function (): void {
    $porRevisar = ticketMinaPisco();
    $enTaller = ticketMinaPisco();
    RevisionTaller::factory()->noApta()->create(['vuelta_id' => $enTaller->id]);

    $pendientes = pendientesDe('taller');

    expect(array_keys($pendientes))->toBe(['por_revisar', 'no_aptos'])
        ->and($pendientes['por_revisar']['cantidad'])->toBe(1)
        ->and($pendientes['por_revisar']['items'][0]['texto'])->toContain($porRevisar->codigo)
        ->and($pendientes['no_aptos']['cantidad'])->toBe(1);
});

it('shows fuel what leaves without its fuel, only once the workshop said yes', function (): void {
    $sinOk = ticketMinaPisco();
    $apto = ticketMinaPisco();
    RevisionTaller::factory()->create(['vuelta_id' => $apto->id]);

    $pendientes = pendientesDe('abastecimiento');

    expect($pendientes['combustible_ida']['cantidad'])->toBe(1)
        ->and($pendientes['combustible_ida']['items'][0]['href'])->toContain('ticket='.$apto->id)
        ->and($pendientes['combustible_vuelta']['cantidad'])->toBe(0)
        ->and(collect($pendientes)->pluck('items')->flatten(1)->pluck('texto')->implode(' '))->not->toContain($sinOk->codigo);
});

it('shows programming the late departures, the returns to define and what to lock', function (): void {
    ticketMinaPisco(['inicio' => now()->subDays(3)->toDateString()]);

    $enIda = ticketMinaPisco(['estado' => EstadoVuelta::EnCurso]);
    Viaje::factory()->create(['tracto_id' => null, 'fecha_traslado' => now()->subDay()->toDateString()])
        ->forceFill(['parada_vuelta_id' => $enIda->paradas()->first()->id])->save();

    $llego = ticketMinaPisco(['estado' => EstadoVuelta::EnCurso, 'origen' => OrigenVuelta::Programacion]);
    $regreso = ParadaVuelta::factory()->create([
        'vuelta_id' => $llego->id, 'sentido' => SentidoTramo::Regreso, 'orden' => 2,
        'zona_origen' => ZonaRuta::Pisco, 'zona_destino' => ZonaRuta::Mina, 'tipo_carga' => TipoCarga::Escoria,
    ]);
    Viaje::factory()->create(['tracto_id' => null, 'fecha_traslado' => now()->subDays(5)->toDateString()])
        ->forceFill(['parada_vuelta_id' => $regreso->id])->save();

    $pendientes = pendientesDe('admin');

    expect($pendientes['atrasadas']['cantidad'])->toBe(1)
        ->and($pendientes['regresos']['cantidad'])->toBe(1)
        ->and($pendientes['por_cerrar']['cantidad'])->toBe(1)
        ->and($pendientes['en_base']['cantidad'])->toBe(1);
});

it('shows billing only the GR without invoice', function (): void {
    Viaje::factory()->count(2)->create(['tracto_id' => null]);

    $pendientes = pendientesDe('contador');

    expect(array_keys($pendientes))->toBe(['sin_facturar'])
        ->and($pendientes['sin_facturar']['cantidad'])->toBe(2)
        ->and($pendientes['sin_facturar']['href'])->toContain('estado=sin_facturar');
});

it('keeps the month summary on the same page', function (): void {
    actingAs(actorConRol('admin'))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->component('dashboard')->has('metaConcentrado')->has('pendientes'));
});
