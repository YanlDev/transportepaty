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
use App\Models\Viatico;
use App\Models\Vuelta;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

/**
 * La GR de regreso se emite al cargar (la de Ransa, en el Callao): la unidad
 * sigue en vuelta hasta llegar, y el ticket recibe combustible hasta que
 * alguien le pone el candado.
 */
beforeEach(function (): void {
    foreach (['admin', 'operaciones'] as $rol) {
        Role::findOrCreate($rol, 'web');
    }

    $this->tracto = Vehiculo::factory()->create(['tipo' => TipoVehiculo::Tracto]);
});

/**
 * Un ticket apto de taller cuya GR de Ransa (Lima → mina) salió ese día.
 */
function ticketConRansa(Vehiculo $tracto, string $fechaGr): Vuelta
{
    $vuelta = Vuelta::factory()->create(['tracto_id' => $tracto->id, 'inicio' => now()->subDays(10)->toDateString()]);
    RevisionTaller::factory()->create(['vuelta_id' => $vuelta->id]);
    $parada = ParadaVuelta::factory()->create([
        'vuelta_id' => $vuelta->id, 'sentido' => SentidoTramo::Regreso, 'orden' => 1,
        'zona_origen' => ZonaRuta::Lima, 'zona_destino' => ZonaRuta::Mina, 'tipo_carga' => TipoCarga::Materiales,
    ]);
    Viaje::factory()->create(['tracto_id' => null, 'fecha_traslado' => $fechaGr])
        ->forceFill(['parada_vuelta_id' => $parada->id])->save();

    return $vuelta->load('paradas.viajes');
}

it('keeps the unit on its way back until it arrives, not when the GR is issued', function (): void {
    expect(ticketConRansa($this->tracto, now()->toDateString())->etapa())->toBe('en_vuelta')
        ->and(ticketConRansa(Vehiculo::factory()->create(['tipo' => TipoVehiculo::Tracto]), now()->subDays(3)->toDateString())->etapa())->toBe('en_base');
});

it('still lets fuel be registered at Kio after the Ransa GR', function (): void {
    $vuelta = ticketConRansa($this->tracto, now()->toDateString());

    actingAs(actorConRol('abastecimiento'))
        ->get(route('abastecimiento.index'))
        ->assertInertia(fn (Assert $page) => $page->where('vueltas.0.id', $vuelta->id)->where('vueltas.0.etapa', 'en_vuelta'));

    actingAs(actorConRol('abastecimiento'))
        ->post(route('abastecimiento.store'), ['vehiculo_id' => $this->tracto->id, 'vuelta_id' => $vuelta->id, 'fecha' => now()->toDateString(), 'galones' => 80])
        ->assertSessionHasNoErrors();
});

it('shows the tickets that arrived at base in the last days while they are open', function (): void {
    $reciente = ticketConRansa($this->tracto, now()->subDays(5)->toDateString());
    $vieja = ticketConRansa(Vehiculo::factory()->create(['tipo' => TipoVehiculo::Tracto]), now()->subDays(20)->toDateString());

    actingAs(actorConRol('abastecimiento'))
        ->get(route('abastecimiento.index'))
        ->assertInertia(fn (Assert $page) => $page->where('vueltas', fn ($vueltas): bool => collect($vueltas)->pluck('id')->all() === [$reciente->id]));

    expect($vieja->etapa())->toBe('en_base');
});

it('locks a ticket: no more fuel nor allowances until it is reopened', function (): void {
    $vuelta = ticketConRansa($this->tracto, now()->toDateString());

    actingAs(actorConRol('admin'))->post(route('vueltas.cerrar', $vuelta))->assertSessionHas('toast.type', 'success');

    expect($vuelta->fresh()->estado)->toBe(EstadoVuelta::Cerrada);

    actingAs(actorConRol('abastecimiento'))
        ->post(route('abastecimiento.store'), ['vehiculo_id' => $this->tracto->id, 'vuelta_id' => $vuelta->id, 'fecha' => now()->toDateString(), 'galones' => 80])
        ->assertSessionHasErrors('vehiculo_id');

    actingAs(actorConRol('abastecimiento'))
        ->post(route('viaticos.store'), ['vuelta_id' => $vuelta->id, 'concepto' => 'Imprevisto', 'monto' => 50, 'fecha' => now()->toDateString()])
        ->assertSessionHasErrors('vuelta_id');

    actingAs(actorConRol('admin'))->post(route('vueltas.reabrir', $vuelta))->assertSessionHas('toast.type', 'success');

    expect($vuelta->fresh()->estado)->toBe(EstadoVuelta::EnCurso);

    actingAs(actorConRol('abastecimiento'))
        ->post(route('abastecimiento.store'), ['vehiculo_id' => $this->tracto->id, 'vuelta_id' => $vuelta->id, 'fecha' => now()->toDateString(), 'galones' => 80])
        ->assertSessionHasNoErrors();
});

it('does not touch what a locked ticket already has', function (): void {
    $vuelta = ticketConRansa($this->tracto, now()->toDateString());
    $abastecimiento = Abastecimiento::factory()->create(['vehiculo_id' => $this->tracto->id, 'vuelta_id' => $vuelta->id]);
    $viatico = Viatico::factory()->create(['vuelta_id' => $vuelta->id, 'vehiculo_id' => $this->tracto->id]);
    $vuelta->update(['estado' => EstadoVuelta::Cerrada]);

    actingAs(actorConRol('abastecimiento'))->delete(route('abastecimiento.destroy', $abastecimiento))->assertForbidden();
    actingAs(actorConRol('abastecimiento'))->post(route('viaticos.anular', $viatico), ['motivo' => 'error'])->assertForbidden();
});

it('only lets whoever has the permission lock, and reopens only the latest ticket', function (): void {
    $vuelta = ticketConRansa($this->tracto, now()->subDays(5)->toDateString());

    actingAs(actorConRol('abastecimiento'))->post(route('vueltas.cerrar', $vuelta))->assertForbidden();

    $vuelta->update(['estado' => EstadoVuelta::Cerrada]);
    Vuelta::factory()->programada()->create(['tracto_id' => $this->tracto->id, 'inicio' => now()->toDateString()]);

    actingAs(actorConRol('admin'))->post(route('vueltas.reabrir', $vuelta))->assertForbidden();
});
