<?php

use App\Enums\SentidoTramo;
use App\Enums\TipoCarga;
use App\Enums\TipoVehiculo;
use App\Models\Vehiculo;
use App\Models\Viaje;
use App\Models\Vuelta;

use function Pest\Laravel\actingAs;

/**
 * Juliaca es la única base: la unidad que trajo escoria a la mina arranca
 * su ticket siguiente en Juliaca, subiendo con la misma GR, y la ida suma
 * los 10 galones de la subida con escoria (230).
 */
it('programa la subida con la escoria que trae y suma los 10 galones', function (): void {
    $tracto = Vehiculo::factory()->create(['tipo' => TipoVehiculo::Tracto, 'placa' => 'ESC-230']);
    $escoria = Viaje::factory()->create([
        'tracto_id' => $tracto->id,
        'numero_gr' => 'EG07-000123',
        'tipo_carga' => TipoCarga::Escoria,
        'fecha_traslado' => now()->subDays(2),
        'origen' => 'KM 245 - PARACAS - PISCO - ICA',
        'destino' => 'SAN RAFAEL - ANTAUTA - MELGAR - PUNO',
    ]);
    actingAs(actorConRol('admin'));

    visit("/vueltas/unidades/{$tracto->id}")
        ->on()->desktop()
        ->click('Nuevo ticket')
        ->click('Concentrado a Pisco')
        ->assertSee('Sigue con la escoria que trae (GR EG07-000123)')
        ->assertSee('Con escoria se suma «Sube con escoria» (+10 gal).')
        ->click('Programar')
        ->assertNoJavaScriptErrors();

    $ticket = Vuelta::query()->where('tracto_id', $tracto->id)->sole();

    expect($ticket->paradasDe(SentidoTramo::Ida)->first()->viaje_continuado_id)->toBe($escoria->id)
        ->and($ticket->topeDe(SentidoTramo::Ida))->toBe(230.0);
});
