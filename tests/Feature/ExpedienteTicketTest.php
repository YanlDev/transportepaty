<?php

use App\Enums\EstadoVuelta;
use App\Enums\SentidoTramo;
use App\Enums\TipoCarga;
use App\Enums\TipoVehiculo;
use App\Enums\ZonaRuta;
use App\Models\Abastecimiento;
use App\Models\ParadaVuelta;
use App\Models\RevisionTaller;
use App\Models\Vehiculo;
use App\Models\Viaje;
use App\Models\Vuelta;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

/**
 * El expediente del ticket: la línea de pasos dice en cuál va, quién hizo
 * cada uno, y las pestañas traen sus GR y lo que se gastó.
 */
beforeEach(function (): void {
    foreach (['admin', 'contador'] as $rol) {
        Role::findOrCreate($rol, 'web');
    }

    $this->tracto = Vehiculo::factory()->create(['tipo' => TipoVehiculo::Tracto]);
    $this->vuelta = Vuelta::factory()->programada()->create(['tracto_id' => $this->tracto->id, 'inicio' => now()->subDays(2)->toDateString()]);
    $this->ida = ParadaVuelta::factory()->create([
        'vuelta_id' => $this->vuelta->id, 'sentido' => SentidoTramo::Ida, 'orden' => 1,
        'zona_origen' => ZonaRuta::Mina, 'zona_destino' => ZonaRuta::Pisco, 'tipo_carga' => TipoCarga::Concentrado,
        'lugar_origen' => null, 'lugar_destino' => null,
    ]);
});

/**
 * @return array<string, array<string, mixed>>
 */
function pasosDe(Vuelta $vuelta): array
{
    $pagina = actingAs(actorConRol('admin'))->get(route('vueltas.show', $vuelta))->assertOk();

    return collect($pagina->viewData('page')['props']['pasos'])->keyBy('clave')->all();
}

it('waits for the workshop right after it is programmed', function (): void {
    actingAs(actorConRol('admin'))
        ->get(route('vueltas.show', $this->vuelta))
        ->assertInertia(fn (Assert $page) => $page
            ->component('vueltas/show')
            ->where('ticket.id', $this->vuelta->id)
            ->where('actual', 'taller')
            ->has('pasos', 9));
});

it('shows a unit held back by the workshop as an alert', function (): void {
    RevisionTaller::factory()->noApta()->create(['vuelta_id' => $this->vuelta->id]);

    expect(pasosDe($this->vuelta)['taller']['estado'])->toBe('alerta');
});

it('moves on to the return once it is fit, fueled and has its outbound GR', function (): void {
    RevisionTaller::factory()->create(['vuelta_id' => $this->vuelta->id]);
    Abastecimiento::factory()->create(['vehiculo_id' => $this->tracto->id, 'vuelta_id' => $this->vuelta->id, 'galones' => 500]);
    Viaje::factory()->create(['tracto_id' => null, 'fecha_traslado' => now()->subDay()->toDateString()])
        ->forceFill(['parada_vuelta_id' => $this->ida->id])->save();
    $this->vuelta->update(['estado' => EstadoVuelta::EnCurso]);

    $pasos = pasosDe($this->vuelta);

    expect($pasos['taller']['estado'])->toBe('hecho')
        ->and($pasos['en_ida']['estado'])->toBe('hecho')
        ->and($pasos['regreso']['estado'])->toBe('pendiente');

    actingAs(actorConRol('admin'))
        ->get(route('vueltas.show', $this->vuelta))
        ->assertInertia(fn (Assert $page) => $page
            ->has('guias', 1)
            ->where('gastos.total_registrado', 500));
});

it('has no current step once it is locked', function (): void {
    $this->vuelta->update(['estado' => EstadoVuelta::Cerrada, 'fin' => now()->toDateString()]);

    actingAs(actorConRol('admin'))
        ->get(route('vueltas.show', $this->vuelta))
        ->assertInertia(fn (Assert $page) => $page->where('actual', null));

    expect(pasosDe($this->vuelta)['cerrado']['estado'])->toBe('hecho');
});

it('is read by whoever reads the programación', function (): void {
    actingAs(actorConRol('rrhh'))->get(route('vueltas.show', $this->vuelta))->assertForbidden();
    // Contabilidad lo ve: liquida los tickets.
    actingAs(actorConRol('contador'))->get(route('vueltas.show', $this->vuelta))->assertSuccessful();
});

it('tells the board what each ticket needs next', function (): void {
    actingAs(actorConRol('admin'))
        ->get(route('vueltas.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tablero.0.id', $this->vuelta->id)
            ->where('tablero.0.siguiente.clave', 'taller')
            ->where('tablero.0.siguiente.detalle', 'Esperando la revisión'));
});

it('does not count programming the return leg as progress', function (): void {
    // Se programó con la vuelta ya sabida (escoria): todavía no sale.
    ParadaVuelta::factory()->create([
        'vuelta_id' => $this->vuelta->id, 'sentido' => SentidoTramo::Regreso, 'orden' => 2,
        'zona_origen' => ZonaRuta::Pisco, 'zona_destino' => ZonaRuta::Mina, 'tipo_carga' => TipoCarga::Escoria,
        'lugar_origen' => null, 'lugar_destino' => null,
    ]);

    actingAs(actorConRol('admin'))
        ->get(route('vueltas.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tablero.0.siguiente.clave', 'taller')
            ->where('tablero.0.saltados', []));
});

it('does not list the outbound steps as skipped when the ticket has no outbound leg', function (): void {
    // La unidad ya estaba en la costa cuando empezaron los tickets: el
    // ticket arranca en Lima con la vuelta (particular a Arequipa).
    $this->ida->update([
        'sentido' => SentidoTramo::Regreso, 'zona_origen' => ZonaRuta::Lima, 'zona_destino' => ZonaRuta::Arequipa, 'tipo_carga' => TipoCarga::Particular,
    ]);
    RevisionTaller::factory()->create(['vuelta_id' => $this->vuelta->id]);

    expect(pasosDe($this->vuelta))->not->toHaveKeys(['abastecimiento_ida', 'en_ida'])
        ->toHaveKeys(['taller', 'regreso', 'abastecimiento_vuelta', 'en_vuelta']);

    actingAs(actorConRol('admin'))
        ->get(route('vueltas.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tablero.0.siguiente.clave', 'abastecimiento_vuelta')
            ->where('tablero.0.saltados', []));
});

it('follows on from where the unit is, and lists what was skipped', function (): void {
    // Ya va en ida, salió sin el OK de taller ni combustible registrado.
    Viaje::factory()->create(['tracto_id' => null, 'fecha_traslado' => now()->subDay()->toDateString()])
        ->forceFill(['parada_vuelta_id' => $this->ida->id])->save();
    $this->vuelta->update(['estado' => EstadoVuelta::EnCurso]);

    actingAs(actorConRol('admin'))
        ->get(route('vueltas.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tablero.0.siguiente.clave', 'regreso')
            ->where('tablero.0.saltados', ['OK de taller', 'Abastecimiento de ida']));

    actingAs(actorConRol('admin'))
        ->get(route('vueltas.show', $this->vuelta))
        ->assertInertia(fn (Assert $page) => $page->where('actual', 'regreso'));
});
