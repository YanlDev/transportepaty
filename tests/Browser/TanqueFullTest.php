<?php

use App\Enums\SentidoTramo;
use App\Enums\TipoVehiculo;
use App\Models\RevisionTaller;
use App\Models\RutaVuelta;
use App\Models\Vehiculo;
use App\Models\Vuelta;

use function Pest\Laravel\actingAs;

/**
 * Un ticket cuya vuelta tiene tanque full en Kio: el tablero lo muestra
 * aparte del tope, y al autorizar se ofrece llenarlo sin galones.
 */
it('muestra el tanque full aparte del tope y lo ofrece al autorizar', function (): void {
    $tracto = Vehiculo::factory()->create(['tipo' => TipoVehiculo::Tracto, 'placa' => 'FUL-190']);
    $vuelta = Vuelta::factory()->create(['tracto_id' => $tracto->id, 'inicio' => now()->subDay()->toDateString()]);
    RevisionTaller::factory()->create(['vuelta_id' => $vuelta->id]);
    $ruta = rutaDelCatalogo('Particular a Juliaca')->load(['lineas.punto', 'variantes']);
    $vuelta->rutas()->create([...RutaVuelta::copiaDe($ruta, null), 'sentido' => SentidoTramo::Regreso, 'orden' => 1]);

    actingAs(actorConRol('admin'));

    visit('/abastecimiento')
        ->on()->desktop()
        ->assertSee('FUL190')
        ->assertSee('+ tanque full en Kio · pendiente')
        ->assertNoJavaScriptErrors()
        ->screenshot(fullPage: true, filename: 'tanque-full-tablero');
});
