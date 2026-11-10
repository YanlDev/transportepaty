<?php

use App\Enums\SentidoTramo;
use App\Enums\TipoVehiculo;
use App\Models\ParadaVuelta;
use App\Models\RutaVuelta;
use App\Models\Vehiculo;
use App\Models\Vuelta;

use function Pest\Laravel\actingAs;

/**
 * Un desvío que no se repite: Programación le suma galones al tramo desde el
 * panel del ticket, con su motivo, y el tope sube.
 */
it('agrega combustible adicional a un tramo desde el panel del ticket', function (): void {
    $tracto = Vehiculo::factory()->create(['tipo' => TipoVehiculo::Tracto, 'placa' => 'ADI-020']);
    $vuelta = Vuelta::factory()->programada()->create(['tracto_id' => $tracto->id, 'inicio' => now()->toDateString()]);
    ParadaVuelta::factory()->create(['vuelta_id' => $vuelta->id, 'sentido' => SentidoTramo::Regreso, 'orden' => 1, 'zona_origen' => 'lima', 'zona_destino' => 'juliaca', 'tipo_carga' => 'particular', 'lugar_origen' => null, 'lugar_destino' => null]);
    $vuelta->rutas()->create([...RutaVuelta::copiaDe(rutaDelCatalogo('Particular a Juliaca')->load(['lineas.punto', 'variantes']), null), 'sentido' => SentidoTramo::Regreso, 'orden' => 1]);

    actingAs(actorConRol('admin'));

    visit('/vueltas')
        ->on()->desktop()
        ->click('ADI020')
        ->click('button:has-text("Sumar al tramo")')
        ->click('button:has-text("Galones sueltos")')
        ->type('#agregar-adicional input[type="number"]', '20')
        ->type('#agregar-adicional textarea', 'Descarga en Lurín antes de subir')
        ->click('Agregar')
        ->assertSee('+20 gal')
        ->assertSee('Descarga en Lurín antes de subir')
        ->assertNoJavaScriptErrors()
        ->screenshot(fullPage: true, filename: 'adicional-tramo');

    expect($vuelta->fresh()->topeDe(SentidoTramo::Regreso))->toBe(210.0);
});
