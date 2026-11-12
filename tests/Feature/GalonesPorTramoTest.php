<?php

use App\Enums\SentidoTramo;
use App\Enums\TipoCarga;
use App\Enums\ZonaRuta;
use App\Models\GalonesPorTramo;
use App\Models\ParadaVuelta;
use App\Models\PuntoAbastecimiento;
use App\Models\RutaVuelta;
use App\Models\Vuelta;
use App\Services\TablaDeTramos;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

/**
 * Galones por tramo: Juliaca es la base; un viaje particular es hasta el
 * destino cargado y de ahí a Juliaca vacío (o cargado si trae algo). La
 * tabla dice cuánto gasta cada tramo, y la ruta por tramos suma los suyos.
 */
function paradaDelTramo(Vuelta $vuelta, int $orden, ZonaRuta $desde, ZonaRuta $hasta, TipoCarga $carga, ?string $lugarOrigen = null): ParadaVuelta
{
    return ParadaVuelta::factory()->create([
        'vuelta_id' => $vuelta->id, 'sentido' => SentidoTramo::Regreso, 'orden' => $orden,
        'zona_origen' => $desde, 'zona_destino' => $hasta, 'tipo_carga' => $carga,
        'lugar_origen' => $lugarOrigen, 'lugar_destino' => null,
    ]);
}

function conRutaDelCatalogo(Vuelta $vuelta, string $nombre, int $orden = 1): RutaVuelta
{
    $ruta = rutaDelCatalogo($nombre)->load(['lineas.punto', 'variantes']);

    return $vuelta->rutas()->create([...RutaVuelta::copiaDe($ruta, null), 'sentido' => SentidoTramo::Regreso, 'orden' => $orden]);
}

it('adds up the catalog routes from the table: Lima → Arequipa 150 + vacía a Juliaca 20', function (): void {
    $arequipa = rutaDelCatalogo('Particular a Arequipa')->load(['lineas.punto', 'variantes']);
    $juliaca = rutaDelCatalogo('Particular a Juliaca')->load(['lineas.punto', 'variantes']);

    expect($arequipa->por_tramos)->toBeTrue()
        ->and($arequipa->topePara(null))->toBe(170.0)
        ->and($juliaca->topePara(null))->toBe(190.0)
        // El full de Kio y los viáticos siguen como líneas de la ruta.
        ->and(collect($juliaca->lineasPara(null))->where('tanque_lleno', true)->pluck('punto')->all())->toBe(['Kio'])
        ->and(collect($arequipa->lineasPara(null))->where('tramo', true)->pluck('concepto')->all())
        ->toBe(['Lima → Arequipa · cargado', 'Arequipa → Juliaca · vacío']);
});

it('follows the real trip: the rice GR turns the empty leg into a loaded one and the cap goes up', function (): void {
    $alca = Vuelta::factory()->programada()->create(['inicio' => now()->toDateString()]);
    paradaDelTramo($alca, 1, ZonaRuta::Lima, ZonaRuta::Arequipa, TipoCarga::Particular, 'Huaral');
    $vacia = paradaDelTramo($alca, 2, ZonaRuta::Arequipa, ZonaRuta::Juliaca, TipoCarga::Vacio);
    conRutaDelCatalogo($alca, 'Particular a Arequipa');

    expect($alca->fresh()->topeDe(SentidoTramo::Regreso))->toBe(170.0);

    // La GR del arroz (Uraca → Juliaca) hace ir cargado el tramo: 150 + 40.
    $vacia->update(['tipo_carga' => TipoCarga::Particular, 'lugar_origen' => 'Uraca']);
    expect($alca->fresh()->topeDe(SentidoTramo::Regreso))->toBe(190.0);

    // Los desvíos se suman aparte.
    conRutaDelCatalogo($alca, 'Ida y vuelta Huaral', 2);
    conRutaDelCatalogo($alca, 'Uraca (Corire)', 3);
    expect($alca->fresh()->topeDe(SentidoTramo::Regreso))->toBe(250.0);
});

it('does not count the stops that a complement adds (Ilo → Juliaca with flour)', function (): void {
    $vuelta = Vuelta::factory()->programada()->create(['inicio' => now()->toDateString()]);
    paradaDelTramo($vuelta, 1, ZonaRuta::Lima, ZonaRuta::Juliaca, TipoCarga::Particular);
    paradaDelTramo($vuelta, 2, ZonaRuta::Moquegua, ZonaRuta::Juliaca, TipoCarga::Particular, 'Ilo');
    conRutaDelCatalogo($vuelta, 'Particular a Juliaca');

    expect($vuelta->fresh()->topeDe(SentidoTramo::Regreso))->toBe(190.0);
});

it('counts the leg as programmed while the ticket has no stop for it yet', function (): void {
    $vuelta = Vuelta::factory()->programada()->create(['inicio' => now()->toDateString()]);
    conRutaDelCatalogo($vuelta, 'Particular a Arequipa');

    expect($vuelta->fresh()->topeDe(SentidoTramo::Regreso))->toBe(170.0);
});

it('takes the new numbers of the table, and leaves the leg without a cap when one is missing', function (): void {
    $vuelta = Vuelta::factory()->programada()->create(['inicio' => now()->toDateString()]);
    paradaDelTramo($vuelta, 1, ZonaRuta::Lima, ZonaRuta::Arequipa, TipoCarga::Particular);
    paradaDelTramo($vuelta, 2, ZonaRuta::Arequipa, ZonaRuta::Juliaca, TipoCarga::Vacio);
    conRutaDelCatalogo($vuelta, 'Particular a Arequipa');

    GalonesPorTramo::query()->where('zona_origen', 'arequipa')->where('zona_destino', 'juliaca')->update(['galones_vacio' => 25]);
    app(TablaDeTramos::class)->olvidar();
    expect($vuelta->fresh()->topeDe(SentidoTramo::Regreso))->toBe(175.0);

    GalonesPorTramo::query()->where('zona_origen', 'arequipa')->where('zona_destino', 'juliaca')->first()->update(['galones_vacio' => null]);
    expect($vuelta->fresh()->topeDe(SentidoTramo::Regreso))->toBeNull();
});

it('moves the open tickets of those routes to tramos', function (): void {
    $vuelta = Vuelta::factory()->programada()->create(['inicio' => now()->toDateString()]);
    paradaDelTramo($vuelta, 1, ZonaRuta::Lima, ZonaRuta::Arequipa, TipoCarga::Particular);
    paradaDelTramo($vuelta, 2, ZonaRuta::Arequipa, ZonaRuta::Juliaca, TipoCarga::Vacio);
    // Una copia de antes: total a mano de 190 «Hasta Juliaca».
    $copia = $vuelta->rutas()->create([
        'ruta_id' => rutaDelCatalogo('Particular a Arequipa')->id, 'sentido' => SentidoTramo::Regreso, 'orden' => 1,
        'nombre' => 'Particular a Arequipa', 'es_complemento' => false, 'por_tramos' => false, 'variante_pendiente' => false,
        'lineas' => [['linea_id' => null, 'tipo' => 'combustible', 'concepto' => 'Hasta Juliaca', 'punto_id' => null, 'punto' => null, 'galones' => 190, 'tanque_lleno' => false, 'monto' => null]],
    ]);
    expect($vuelta->fresh()->topeDe(SentidoTramo::Regreso))->toBe(190.0);

    (require database_path('migrations/2026_11_12_143520_pasa_las_rutas_particulares_a_tramos.php'))->up();

    expect($copia->fresh()->por_tramos)->toBeTrue()
        ->and($vuelta->fresh()->topeDe(SentidoTramo::Regreso))->toBe(170.0);
});

it('lets whoever configures the routes edit the table', function (): void {
    actingAs(actorConRol('contador'))->get(route('rutas.tramos'))->assertForbidden();

    actingAs(actorConRol('admin'))
        ->get(route('rutas.tramos'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('rutas/tramos')
            ->where('filas', fn ($filas) => collect($filas)->contains(fn ($fila) => $fila['zona_origen'] === 'arequipa'
                && $fila['zona_destino'] === 'juliaca'
                && (float) $fila['galones_vacio'] === 20.0
                && in_array('Particular a Arequipa', $fila['rutas'], true))));

    actingAs(actorConRol('admin'))->put(route('rutas.tramos.guardar'), ['filas' => [
        ['zona_origen' => 'lima', 'zona_destino' => 'arequipa', 'galones_cargado' => 150, 'galones_vacio' => null],
        ['zona_origen' => 'arequipa', 'zona_destino' => 'juliaca', 'galones_cargado' => 40, 'galones_vacio' => 22, 'nota' => 'Confirmado'],
        ['zona_origen' => 'lima', 'zona_destino' => 'cusco', 'galones_cargado' => 200, 'galones_vacio' => null],
    ]])->assertSessionHasNoErrors();

    // Lima → Juliaca ya no está: se quitó; Arequipa → Juliaca vacío pasó a 22.
    expect(GalonesPorTramo::query()->count())->toBe(3)
        ->and(app(TablaDeTramos::class)->galones(ZonaRuta::Arequipa, ZonaRuta::Juliaca, false))->toBe(22.0)
        ->and(rutaDelCatalogo('Particular a Arequipa')->load(['lineas.punto', 'variantes'])->topePara(null))->toBe(172.0);
});

it('rejects the same leg twice and a leg that stays in its zone', function (): void {
    actingAs(actorConRol('admin'))->put(route('rutas.tramos.guardar'), ['filas' => [
        ['zona_origen' => 'lima', 'zona_destino' => 'arequipa', 'galones_cargado' => 150],
        ['zona_origen' => 'lima', 'zona_destino' => 'arequipa', 'galones_cargado' => 160],
        ['zona_origen' => 'lima', 'zona_destino' => 'lima', 'galones_cargado' => 10],
    ]])->assertSessionHasErrors(['filas.1.zona_destino', 'filas.2.zona_destino']);
});

it('saves a route by tramos keeping only the full tank among its fuel lines', function (): void {
    actingAs(actorConRol('admin'))->post(route('rutas.store'), [
        'nombre' => 'Particular a Cusco por tramos',
        'sentido' => 'regreso',
        'es_complemento' => false,
        'por_tramos' => true,
        'activa' => true,
        'paradas' => [
            ['zona_origen' => 'lima', 'zona_destino' => 'cusco', 'tipo_carga' => 'particular'],
            ['zona_origen' => 'cusco', 'zona_destino' => 'juliaca', 'tipo_carga' => 'vacio'],
        ],
        'lineas' => [
            ['id' => null, 'variante' => null, 'tipo' => 'combustible', 'concepto' => 'Combustible', 'punto_abastecimiento_id' => PuntoAbastecimiento::query()->where('nombre', 'Kio')->value('id'), 'tanque_lleno' => true],
            ['id' => null, 'variante' => null, 'tipo' => 'combustible', 'concepto' => 'Hasta Juliaca', 'galones' => 200],
            ['id' => null, 'variante' => null, 'tipo' => 'viatico', 'concepto' => 'Viáticos', 'monto' => 500],
        ],
    ])->assertSessionHasNoErrors();

    $ruta = rutaDelCatalogo('Particular a Cusco por tramos')->load(['lineas.punto', 'variantes']);

    expect($ruta->por_tramos)->toBeTrue()
        ->and($ruta->lineas->pluck('concepto')->all())->toBe(['Combustible', 'Viáticos'])
        // Lima → Cusco y Cusco → Juliaca no están en la tabla: sin tope.
        ->and($ruta->topePara(null))->toBeNull();
});
