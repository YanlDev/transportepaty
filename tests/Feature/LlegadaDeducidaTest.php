<?php

use App\Enums\EstadoVuelta;
use App\Enums\OrigenVuelta;
use App\Enums\SentidoTramo;
use App\Enums\TipoCarga;
use App\Enums\TipoVehiculo;
use App\Enums\ZonaRuta;
use App\Models\ParadaVuelta;
use App\Models\Vehiculo;
use App\Models\Viaje;
use App\Models\Vuelta;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;

/**
 * Si la unidad vuelve a salir es que llegó: el ticket que seguía en ruta
 * queda con la llegada el día antes de la nueva salida, sin que nadie la
 * registre.
 */
beforeEach(function (): void {
    Role::findOrCreate('admin', 'web');

    $this->tracto = Vehiculo::factory()->create(['tipo' => TipoVehiculo::Tracto]);
});

/**
 * Un ticket en ruta de la unidad, con una GR de regreso ese día si se pide.
 */
function ticketEnRuta(Vehiculo $tracto, string $inicio, ?string $fechaGr = null): Vuelta
{
    $vuelta = Vuelta::factory()->create(['tracto_id' => $tracto->id, 'origen' => OrigenVuelta::Programacion, 'inicio' => $inicio]);

    if ($fechaGr !== null) {
        $parada = ParadaVuelta::factory()->create([
            'vuelta_id' => $vuelta->id, 'sentido' => SentidoTramo::Regreso, 'orden' => 1,
            'zona_origen' => ZonaRuta::Lima, 'zona_destino' => ZonaRuta::Mina, 'tipo_carga' => TipoCarga::Materiales,
        ]);
        Viaje::factory()->create(['tracto_id' => null, 'fecha_traslado' => $fechaGr])
            ->forceFill(['parada_vuelta_id' => $parada->id])->save();
    }

    return $vuelta;
}

/**
 * @return array<string, mixed>
 */
function salidaAPisco(Vehiculo $tracto, string $inicio): array
{
    return [
        'tracto_id' => $tracto->id,
        'inicio' => $inicio,
        'paradas' => [['sentido' => 'ida', 'zona_origen' => 'mina', 'zona_destino' => 'pisco', 'tipo_carga' => 'concentrado']],
        'rutas' => [['sentido' => 'ida', 'ruta_id' => rutaDelCatalogo('Concentrado a Pisco')->id, 'variante_ruta_id' => null]],
    ];
}

it('notes the arrival the day before when the unit is programmed to leave again', function (): void {
    $anterior = ticketEnRuta($this->tracto, now()->subDays(12)->toDateString(), now()->subDays(6)->toDateString());

    actingAs(actorConRol('admin'))
        ->post(route('vueltas.store'), salidaAPisco($this->tracto, now()->toDateString()))
        ->assertSessionHasNoErrors();

    $anterior->refresh();

    expect($anterior->llegada_el->toDateString())->toBe(now()->subDay()->toDateString())
        ->and($anterior->llegada_deducida)->toBeTrue()
        ->and($anterior->km_llegada)->toBeNull()
        // Llegar no es cerrar: eso sigue siendo de la liquidación.
        ->and($anterior->estado)->toBe(EstadoVuelta::EnCurso)
        ->and($anterior->etapa())->toBe('en_base');

    actingAs(actorConRol('admin'))
        ->get(route('vueltas.show', $anterior))
        ->assertInertia(fn (Assert $page) => $page->where('ticket.llegada.deducida', true));
});

it('never deduces an arrival before the last GR nor in the future', function (): void {
    $conGuiaTardia = ticketEnRuta($this->tracto, now()->subDays(8)->toDateString(), now()->toDateString());

    actingAs(actorConRol('admin'))
        ->post(route('vueltas.store'), salidaAPisco($this->tracto, now()->toDateString()))
        ->assertSessionHasNoErrors();

    expect($conGuiaTardia->fresh()->llegada_el->toDateString())->toBe(now()->toDateString());

    $otro = Vehiculo::factory()->create(['tipo' => TipoVehiculo::Tracto]);
    $enRuta = ticketEnRuta($otro, now()->subDays(8)->toDateString(), now()->subDays(3)->toDateString());

    actingAs(actorConRol('admin'))
        ->post(route('vueltas.store'), salidaAPisco($otro, now()->addDays(4)->toDateString()))
        ->assertSessionHasNoErrors();

    expect($enRuta->fresh()->llegada_el->toDateString())->toBe(now()->toDateString());
});

it('leaves alone the arrival a person registered', function (): void {
    $anterior = ticketEnRuta($this->tracto, now()->subDays(12)->toDateString(), now()->subDays(6)->toDateString());
    $anterior->update(['llegada_el' => now()->subDays(4)->toDateString(), 'km_llegada' => 251800]);

    actingAs(actorConRol('admin'))
        ->post(route('vueltas.store'), salidaAPisco($this->tracto, now()->toDateString()))
        ->assertSessionHasNoErrors();

    expect($anterior->fresh()->llegada_el->toDateString())->toBe(now()->subDays(4)->toDateString())
        ->and($anterior->fresh()->llegada_deducida)->toBeFalse();
});

it('stops being deduced once a person corrects it', function (): void {
    $anterior = ticketEnRuta($this->tracto, now()->subDays(12)->toDateString(), now()->subDays(6)->toDateString());
    $anterior->update(['llegada_el' => now()->subDay()->toDateString(), 'llegada_deducida' => true]);

    actingAs(actorConRol('admin'))
        ->post(route('vueltas.llegada', $anterior), ['llegada_el' => now()->subDays(2)->toDateString()])
        ->assertSessionHasNoErrors();

    expect($anterior->fresh()->llegada_deducida)->toBeFalse()
        ->and($anterior->fresh()->llegada_el->toDateString())->toBe(now()->subDays(2)->toDateString());
});

it('catches up the tickets whose unit already left again, only with --aplicar', function (): void {
    $anterior = ticketEnRuta($this->tracto, '2026-11-02', '2026-11-06');
    $siguiente = ticketEnRuta($this->tracto, '2026-11-10');
    $otro = ticketEnRuta(Vehiculo::factory()->create(['tipo' => TipoVehiculo::Tracto]), '2026-11-03');

    artisan('transpaty:deducir-llegadas')->assertSuccessful();

    expect($anterior->fresh()->llegada_el)->toBeNull();

    artisan('transpaty:deducir-llegadas', ['--aplicar' => true])->assertSuccessful();

    expect($anterior->fresh()->llegada_el->toDateString())->toBe('2026-11-09')
        ->and($anterior->fresh()->llegada_deducida)->toBeTrue()
        // El último de la unidad y el que no volvió a salir los registra una persona.
        ->and($siguiente->fresh()->llegada_el)->toBeNull()
        ->and($otro->fresh()->llegada_el)->toBeNull();
});
