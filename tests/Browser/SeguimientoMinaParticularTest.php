<?php

use App\Enums\SentidoTramo;
use App\Enums\TipoCarga;
use App\Enums\TipoVehiculo;
use App\Enums\ZonaRuta;
use App\Models\ParadaVuelta;
use App\Models\Vehiculo;
use App\Models\Vuelta;

use function Pest\Laravel\actingAs;

/**
 * En el seguimiento se distingue de un vistazo la carga de mina de la
 * particular (chip por ticket y por tramo) y la ida de la vuelta (rótulo
 * donde empieza cada tramo), y se puede filtrar por clase.
 */
it('distingue los tickets de mina de los particulares y filtra por clase', function (): void {
    $mina = Vuelta::factory()->programada()->create([
        'tracto_id' => Vehiculo::factory()->create(['tipo' => TipoVehiculo::Tracto, 'placa' => 'MIN-220'])->id,
        'inicio' => now()->toDateString(),
    ]);
    $paradaDe = fn (Vuelta $vuelta, SentidoTramo $sentido, int $orden, ZonaRuta $desde, ZonaRuta $hasta, TipoCarga $carga) => ParadaVuelta::factory()->create([
        'vuelta_id' => $vuelta->id, 'sentido' => $sentido, 'orden' => $orden,
        'zona_origen' => $desde, 'zona_destino' => $hasta, 'tipo_carga' => $carga,
        'lugar_origen' => null, 'lugar_destino' => null,
    ]);
    $paradaDe($mina, SentidoTramo::Ida, 1, ZonaRuta::Juliaca, ZonaRuta::Mina, TipoCarga::Vacio);
    $paradaDe($mina, SentidoTramo::Ida, 2, ZonaRuta::Mina, ZonaRuta::Pisco, TipoCarga::Concentrado);
    $paradaDe($mina, SentidoTramo::Regreso, 3, ZonaRuta::Pisco, ZonaRuta::Mina, TipoCarga::Escoria);

    $particular = Vuelta::factory()->programada()->create([
        'tracto_id' => Vehiculo::factory()->create(['tipo' => TipoVehiculo::Tracto, 'placa' => 'PAR-180'])->id,
        'inicio' => now()->toDateString(),
    ]);
    $paradaDe($particular, SentidoTramo::Regreso, 1, ZonaRuta::Lima, ZonaRuta::Arequipa, TipoCarga::Particular);
    $paradaDe($particular, SentidoTramo::Regreso, 2, ZonaRuta::Arequipa, ZonaRuta::Juliaca, TipoCarga::Vacio);

    actingAs(actorConRol('admin'));

    $pagina = visit('/vueltas')
        ->on()->desktop()
        ->assertSee('MIN220')
        ->assertSee('PAR180')
        // Por salir: programar la vuelta no deja atrás el taller.
        ->assertDontSee('Sin: OK de taller')
        ->assertNoJavaScriptErrors()
        ->screenshot(fullPage: true, filename: 'seguimiento-mina-particular');

    // Solo los particulares.
    $pagina->click('[data-filtro-clase="particular"]')
        ->assertSee('PAR180')
        ->assertDontSee('MIN220')
        ->screenshot(fullPage: true, filename: 'seguimiento-solo-particular');
});
