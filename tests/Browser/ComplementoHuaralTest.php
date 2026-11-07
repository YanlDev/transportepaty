<?php

use App\Enums\SentidoTramo;
use App\Enums\TipoVehiculo;
use App\Models\ParadaVuelta;
use App\Models\RevisionTaller;
use App\Models\RutaVuelta;
use App\Models\Vehiculo;
use App\Models\Vuelta;

use function Pest\Laravel\actingAs;

/**
 * Un destino lejano (Huaral, +20 gal) se suma desde la ruta elegida del
 * tramo: el complemento aparece dentro de su tarjeta, no al final de la
 * lista. Caso de BYM852 en prod: vuelta particular a Juliaca.
 */
it('suma Huaral a la vuelta desde la ruta elegida', function (): void {
    $tracto = Vehiculo::factory()->create(['tipo' => TipoVehiculo::Tracto, 'placa' => 'BYM-852']);
    $vuelta = Vuelta::factory()->programada()->create(['tracto_id' => $tracto->id, 'inicio' => now()->toDateString()]);
    ParadaVuelta::factory()->create(['vuelta_id' => $vuelta->id, 'sentido' => SentidoTramo::Regreso, 'orden' => 1, 'zona_origen' => 'lima', 'zona_destino' => 'juliaca', 'tipo_carga' => 'particular', 'lugar_origen' => null, 'lugar_destino' => 'Juliaca']);
    $vuelta->rutas()->create([...RutaVuelta::copiaDe(rutaDelCatalogo('Particular a Juliaca')->load(['lineas.punto', 'variantes']), null), 'sentido' => SentidoTramo::Regreso, 'orden' => 1]);
    RevisionTaller::factory()->create(['vuelta_id' => $vuelta->id]);

    actingAs(actorConRol('admin'));

    visit('/vueltas')
        ->on()->desktop()
        ->click('BYM852')
        ->click('Editar')
        ->click('button:has-text("Ida y vuelta Huaral")')
        ->screenshot(fullPage: true, filename: 'complemento-huaral')
        ->click('Guardar cambios')
        ->assertNoJavaScriptErrors();

    expect($vuelta->fresh()->topeDe(SentidoTramo::Regreso))->toBe(210.0);
});
