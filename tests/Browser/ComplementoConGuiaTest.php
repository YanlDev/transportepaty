<?php

use App\Enums\EstadoVuelta;
use App\Enums\SentidoTramo;
use App\Enums\TipoVehiculo;
use App\Models\ParadaVuelta;
use App\Models\RevisionTaller;
use App\Models\RutaVuelta;
use App\Models\Vehiculo;
use App\Models\Viaje;
use App\Models\Vuelta;

use function Pest\Laravel\actingAs;

/**
 * Un ticket en camino: la vuelta ya tiene GR hasta `$hasta` por la ruta
 * `$ruta`, y lo que sigue de la plantilla queda sin GR.
 *
 * @param  list<array<string, mixed>>  $paradas  La primera lleva la GR.
 */
function ticketConVueltaEmpezada(string $placa, string $ruta, array $paradas): Vuelta
{
    $tracto = Vehiculo::factory()->create(['tipo' => TipoVehiculo::Tracto, 'placa' => $placa]);
    $vuelta = Vuelta::factory()->programada()->create([
        'tracto_id' => $tracto->id,
        'inicio' => now()->subDays(4)->toDateString(),
        'estado' => EstadoVuelta::EnCurso,
    ]);

    foreach ($paradas as $orden => $parada) {
        $creada = ParadaVuelta::factory()->create([
            'vuelta_id' => $vuelta->id,
            'sentido' => SentidoTramo::Regreso,
            'orden' => $orden + 1,
            ...$parada,
        ]);

        if ($orden === 0) {
            Viaje::factory()->create(['tracto_id' => $tracto->id, 'fecha_traslado' => now()->subDays(3)->toDateString()])
                ->forceFill(['parada_vuelta_id' => $creada->id])->save();
        }
    }

    $vuelta->rutas()->create([...RutaVuelta::copiaDe(rutaDelCatalogo($ruta)->load(['lineas.punto', 'variantes']), null), 'sentido' => SentidoTramo::Regreso, 'orden' => 1]);
    RevisionTaller::factory()->create(['vuelta_id' => $vuelta->id]);

    return $vuelta;
}

/**
 * @return list<string>
 */
function paradasDelRegreso(Vuelta $vuelta): array
{
    return $vuelta->paradas()
        ->where('sentido', SentidoTramo::Regreso)
        ->orderBy('orden')
        ->get()
        ->map(fn (ParadaVuelta $parada): string => "{$parada->zona_origen}>{$parada->zona_destino}:{$parada->tipo_carga->value}")
        ->all();
}

/**
 * Caso V-00007 (VEP918): bajó con gaseosa de Huaral a Arequipa y regresa
 * con arroz. Sumar «Camaná (arroz)» al editar no puede volver a meter la
 * plantilla de «Particular a Arequipa» (Lima → Arequipa) delante de lo que
 * ya tiene GR, ni pisar la carga que se puso en «Cargas del recorrido».
 */
it('suma un complemento sin repetir lo que ya tiene GR ni perder lo ajustado a mano', function (): void {
    $vuelta = ticketConVueltaEmpezada('CGA-918', 'Particular a Arequipa', [
        ['zona_origen' => 'lima', 'zona_destino' => 'arequipa', 'tipo_carga' => 'particular', 'lugar_origen' => 'Huaral', 'lugar_destino' => 'Arequipa'],
        ['zona_origen' => 'arequipa', 'zona_destino' => 'juliaca', 'tipo_carga' => 'vacio', 'lugar_origen' => 'Arequipa', 'lugar_destino' => 'Juliaca'],
    ]);

    actingAs(actorConRol('admin'));

    visit("/vueltas/{$vuelta->id}")
        ->on()->desktop()
        ->click('Editar')
        // El vacío a Juliaca consiguió carga: se abre ya como particular.
        ->click('Consiguió carga')
        ->click('Listo')
        ->click('button:has-text("Camaná (arroz)")')
        ->screenshot(fullPage: true, filename: 'complemento-con-guia')
        ->click('Guardar cambios')
        ->assertNoJavaScriptErrors();

    expect(paradasDelRegreso($vuelta))->toBe(['lima>arequipa:particular', 'arequipa>juliaca:particular'])
        ->and($vuelta->rutas()->pluck('nombre')->all())->toContain('Camaná (arroz)');
});

/**
 * Un complemento con paradas propias (harina de Ilo a Juliaca) se suma al
 * final de lo que ya hay y se quita sin tocar el resto.
 */
it('suma y quita las paradas de un complemento en un tramo con GR', function (): void {
    $vuelta = ticketConVueltaEmpezada('CGI-014', 'Particular a Moquegua – Ilo', [
        ['zona_origen' => 'lima', 'zona_destino' => 'moquegua', 'tipo_carga' => 'particular', 'lugar_origen' => 'Lurín', 'lugar_destino' => 'Ilo'],
    ]);

    actingAs(actorConRol('admin'));

    visit("/vueltas/{$vuelta->id}")
        ->on()->desktop()
        ->click('Editar')
        ->click('button:has-text("Ilo a Juliaca con harina")')
        ->click('button:has-text("Ilo a Juliaca con harina")')
        ->click('button:has-text("Ilo a Juliaca con harina")')
        ->click('Guardar cambios')
        ->assertNoJavaScriptErrors();

    expect(paradasDelRegreso($vuelta))->toBe(['lima>moquegua:particular', 'moquegua>juliaca:particular']);
});
