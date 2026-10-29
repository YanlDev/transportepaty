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
    actingAs(actorConRol('contador'))->get(route('vueltas.show', $this->vuelta))->assertForbidden();
});
