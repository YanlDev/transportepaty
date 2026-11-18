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

it('asks for the legs the table does not have and keeps them for the next routes', function (): void {
    GalonesPorTramo::query()->updateOrCreate(
        ['zona_origen' => 'cusco', 'zona_destino' => 'juliaca'],
        ['galones_cargado' => null, 'galones_vacio' => 30],
    );
    GalonesPorTramo::query()->where('zona_origen', 'lima')->where('zona_destino', 'cusco')->delete();

    actingAs(actorConRol('admin'))->post(route('rutas.store'), [
        'nombre' => 'Particular a Cusco nueva',
        'sentido' => 'regreso',
        'por_tramos' => true,
        'activa' => true,
        'paradas' => [
            ['zona_origen' => 'lima', 'zona_destino' => 'cusco', 'tipo_carga' => 'particular'],
            ['zona_origen' => 'cusco', 'zona_destino' => 'juliaca', 'tipo_carga' => 'vacio'],
        ],
        'tramos_nuevos' => [
            ['zona_origen' => 'lima', 'zona_destino' => 'cusco', 'cargado' => true, 'galones' => 170],
            // La tabla ya tiene este número: no se pisa desde una ruta.
            ['zona_origen' => 'cusco', 'zona_destino' => 'juliaca', 'cargado' => false, 'galones' => 99],
        ],
    ])->assertSessionHasNoErrors();

    $tabla = app(TablaDeTramos::class);
    $tabla->olvidar();

    expect($tabla->galones(ZonaRuta::Lima, ZonaRuta::Cusco, true))->toBe(170.0)
        ->and($tabla->galones(ZonaRuta::Lima, ZonaRuta::Cusco, false))->toBeNull()
        ->and($tabla->galones(ZonaRuta::Cusco, ZonaRuta::Juliaca, false))->toBe(30.0)
        ->and(rutaDelCatalogo('Particular a Cusco nueva')->load(['lineas.punto', 'variantes'])->topePara(null))->toBe(200.0);

    actingAs(actorConRol('admin'))->post(route('rutas.store'), [
        'nombre' => 'Mal tramo',
        'por_tramos' => true,
        'paradas' => [['zona_origen' => 'lima', 'zona_destino' => 'cusco', 'tipo_carga' => 'particular']],
        'tramos_nuevos' => [['zona_origen' => 'lima', 'zona_destino' => 'lima', 'cargado' => true, 'galones' => 10]],
    ])->assertSessionHasErrors('tramos_nuevos.0.zona_destino');
});

it('takes the gallons of a loaded leg from its weight band, through the variants of the route', function (): void {
    // La migración deja Lima → Mina por bandas y «Materiales a mina (Ransa)» por tramos.
    $ruta = rutaDelCatalogo('Materiales a mina (Ransa)')->load(['lineas.punto', 'variantes']);
    $tabla = app(TablaDeTramos::class);

    expect($ruta->por_tramos)->toBeTrue()
        ->and($ruta->variantes)->toHaveCount(6)
        ->and($ruta->topePara(null))->toBeNull()
        ->and($ruta->variantes->map(fn ($variante) => $ruta->topePara($variante))->all())->toBe([110.0, 120.0, 130.0, 150.0, 190.0, 190.0])
        ->and($ruta->topePara($ruta->varianteParaPeso(12.5)))->toBe(130.0)
        ->and($tabla->galones(ZonaRuta::Lima, ZonaRuta::Mina, true))->toBeNull()
        ->and($tabla->galones(ZonaRuta::Lima, ZonaRuta::Mina, true, 5))->toBe(110.0)
        ->and($tabla->galones(ZonaRuta::Lima, ZonaRuta::Mina, true, 5.01))->toBe(120.0)
        ->and($tabla->galones(ZonaRuta::Lima, ZonaRuta::Mina, true, 31))->toBe(190.0);

    // En el ticket: sin banda elegida no hay tope; con la banda, el de la tabla.
    $vuelta = Vuelta::factory()->create();
    paradaDelTramo($vuelta, 1, ZonaRuta::Lima, ZonaRuta::Mina, TipoCarga::Materiales);
    $copia = $vuelta->rutas()->create([...RutaVuelta::copiaDe($ruta, null), 'sentido' => SentidoTramo::Regreso, 'orden' => 1]);

    expect($copia->variante_pendiente)->toBeTrue()->and($copia->tope())->toBeNull();

    $copia->update(RutaVuelta::copiaDe($ruta, $ruta->varianteParaPeso(18)));

    expect($copia->refresh()->tope())->toBe(150.0);

    // Cambia la tabla: el ticket abierto toma el número nuevo de su banda.
    $fila = GalonesPorTramo::query()->where('zona_origen', 'lima')->where('zona_destino', 'mina')->whereNull('tipo_carga')->sole();
    $bandas = $fila->bandas;
    $bandas[3]['galones'] = 155;
    $fila->update(['bandas' => $bandas]);

    expect($copia->refresh()->tope())->toBe(155.0);
});

it('edits the weight bands in the table and keeps the route variants in step', function (): void {
    $ruta = rutaDelCatalogo('Particular a mina');
    $ids = $ruta->variantes()->pluck('id', 'nombre');
    $filas = GalonesPorTramo::query()->get()->map(fn (GalonesPorTramo $fila): array => [
        'zona_origen' => $fila->zona_origen->value,
        'zona_destino' => $fila->zona_destino->value,
        'tipo_carga' => $fila->tipo_carga?->value,
        'galones_cargado' => $fila->galones_cargado,
        'galones_vacio' => $fila->galones_vacio,
        'nota' => $fila->nota,
        'bandas' => $fila->zona_destino === ZonaRuta::Mina && $fila->zona_origen === ZonaRuta::Lima && $fila->tipo_carga === TipoCarga::Particular
            ? [
                ['peso_desde' => null, 'peso_hasta' => 5, 'galones' => 110],
                ['peso_desde' => 5, 'peso_hasta' => 20, 'galones' => 140],
                ['peso_desde' => 20, 'peso_hasta' => null, 'galones' => 190],
            ]
            : $fila->bandas,
    ])->all();

    actingAs(actorConRol('admin'))->put(route('rutas.tramos.guardar'), ['filas' => $filas])->assertSessionHasNoErrors();

    $ruta = $ruta->refresh()->load(['lineas.punto', 'variantes']);

    expect($ruta->variantes->pluck('nombre')->all())->toBe(['menos de 5 t', 'hasta 20 t', 'más de 20 t'])
        // La banda que sigue igual conserva su variante: los tickets la nombran.
        ->and($ruta->variantes->first()->id)->toBe($ids['menos de 5 t'])
        ->and($ruta->variantes->map(fn ($variante) => $ruta->topePara($variante))->all())->toBe([110.0, 140.0, 190.0]);

    // La de Ransa no se tocó: es otra fila.
    $ransa = rutaDelCatalogo('Materiales a mina (Ransa)')->load(['lineas.punto', 'variantes']);
    expect($ransa->variantes->map(fn ($variante) => $ransa->topePara($variante))->all())->toBe([110.0, 120.0, 130.0, 150.0, 190.0, 190.0]);

    // Bandas con hueco entre una y otra: no se guardan.
    $filas[array_key_first(array_filter($filas, fn (array $fila): bool => $fila['bandas'] !== null))]['bandas'] = [
        ['peso_desde' => null, 'peso_hasta' => 5, 'galones' => 110],
        ['peso_desde' => 8, 'peso_hasta' => null, 'galones' => 190],
    ];

    actingAs(actorConRol('admin'))->put(route('rutas.tramos.guardar'), ['filas' => $filas])->assertSessionHasErrors();
});

it('keeps the weight variants of a route by tramos when it is saved from the three questions', function (): void {
    $ruta = rutaDelCatalogo('Materiales a mina (Ransa)');
    $ids = $ruta->variantes()->pluck('id')->all();

    actingAs(actorConRol('admin'))->put(route('rutas.update', $ruta), [
        'nombre' => 'Materiales a mina (Ransa)',
        'sentido' => 'regreso',
        'por_tramos' => true,
        'activa' => true,
        'paradas' => [['zona_origen' => 'lima', 'zona_destino' => 'mina', 'tipo_carga' => 'materiales']],
        // El formulario no manda variantes: salen de las bandas de la tabla.
        'variantes' => [],
        'lineas' => [['id' => null, 'variante' => null, 'tipo' => 'viatico', 'concepto' => 'Viáticos', 'monto' => 300]],
    ])->assertSessionHasNoErrors();

    expect($ruta->variantes()->pluck('id')->all())->toBe($ids)
        ->and($ruta->lineas()->pluck('concepto')->all())->toBe(['Viáticos']);
});

it('gives Particular a mina its own table with the Ransa numbers, apart from Ransa', function (): void {
    $tabla = app(TablaDeTramos::class);
    $particular = GalonesPorTramo::query()->where('zona_origen', 'lima')->where('zona_destino', 'mina')->where('tipo_carga', 'particular')->sole();

    expect($particular->bandas)->toBe(GalonesPorTramo::query()->where('zona_origen', 'lima')->where('zona_destino', 'mina')->whereNull('tipo_carga')->sole()->bandas)
        ->and($tabla->galones(ZonaRuta::Lima, ZonaRuta::Mina, true, 1.37, TipoCarga::Particular))->toBe(110.0)
        ->and($tabla->galones(ZonaRuta::Lima, ZonaRuta::Mina, true, 30, TipoCarga::Particular))->toBe(190.0);

    // Un ticket de JS Industrial (particular) y uno de Ransa, con la misma banda.
    $js = Vuelta::factory()->create();
    paradaDelTramo($js, 1, ZonaRuta::Lima, ZonaRuta::Mina, TipoCarga::Particular);
    $rutaParticular = rutaDelCatalogo('Particular a mina')->load(['lineas.punto', 'variantes']);
    $copiaParticular = $js->rutas()->create([...RutaVuelta::copiaDe($rutaParticular, $rutaParticular->varianteParaPeso(18)), 'sentido' => SentidoTramo::Regreso, 'orden' => 1]);

    $ransa = Vuelta::factory()->create();
    paradaDelTramo($ransa, 1, ZonaRuta::Lima, ZonaRuta::Mina, TipoCarga::Materiales);
    $rutaRansa = rutaDelCatalogo('Materiales a mina (Ransa)')->load(['lineas.punto', 'variantes']);
    $copiaRansa = $ransa->rutas()->create([...RutaVuelta::copiaDe($rutaRansa, $rutaRansa->varianteParaPeso(18)), 'sentido' => SentidoTramo::Regreso, 'orden' => 1]);

    expect($copiaParticular->tope())->toBe(150.0)->and($copiaRansa->tope())->toBe(150.0);

    // Cambia solo la de particular: el ticket de Ransa sigue igual.
    $bandas = $particular->bandas;
    $bandas[3]['galones'] = 160;
    $particular->update(['bandas' => $bandas]);

    expect($copiaParticular->refresh()->tope())->toBe(160.0)
        ->and($copiaRansa->refresh()->tope())->toBe(150.0);

    // Un ticket de antes, sin la carga en su línea, la toma de su GR.
    $lineas = $copiaParticular->lineas;
    unset($lineas[0]['tipo_carga']);
    $copiaParticular->update(['lineas' => $lineas]);

    expect($copiaParticular->refresh()->tope())->toBe(160.0);
});

it('lists the leg once per cargo and rejects the same leg twice for the same cargo', function (): void {
    actingAs(actorConRol('admin'))
        ->get(route('rutas.tramos'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filas', fn ($filas) => collect($filas)->contains(fn ($fila) => $fila['zona_origen'] === 'lima'
                && $fila['zona_destino'] === 'mina'
                && $fila['tipo_carga'] === 'particular'
                && $fila['rutas'] === ['Particular a mina'])
                && collect($filas)->contains(fn ($fila) => $fila['zona_origen'] === 'lima'
                    && $fila['zona_destino'] === 'mina'
                    && $fila['tipo_carga'] === null
                    && $fila['rutas'] === ['Materiales a mina (Ransa)'])));

    actingAs(actorConRol('admin'))->put(route('rutas.tramos.guardar'), ['filas' => [
        ['zona_origen' => 'lima', 'zona_destino' => 'mina', 'galones_cargado' => 150],
        ['zona_origen' => 'lima', 'zona_destino' => 'mina', 'tipo_carga' => 'particular', 'galones_cargado' => 160, 'galones_vacio' => 40],
        ['zona_origen' => 'lima', 'zona_destino' => 'mina', 'tipo_carga' => 'particular', 'galones_cargado' => 170],
        ['zona_origen' => 'lima', 'zona_destino' => 'arequipa', 'tipo_carga' => 'vacio', 'galones_cargado' => 10],
    ]])->assertSessionHasErrors(['filas.2.zona_destino', 'filas.3.tipo_carga'])
        ->assertSessionDoesntHaveErrors(['filas.1.zona_destino']);
});

it('sends the route editor every row with its cargo, so Particular a mina shows its own table', function (): void {
    actingAs(actorConRol('admin'))
        ->get(route('rutas.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('opciones.tramos', fn ($tramos) => collect($tramos)->contains(fn ($fila) => $fila['zona_origen'] === 'lima'
                && $fila['zona_destino'] === 'mina'
                && $fila['tipo_carga'] === 'particular')
                && collect($tramos)->contains(fn ($fila) => $fila['zona_origen'] === 'lima'
                    && $fila['zona_destino'] === 'mina'
                    && $fila['tipo_carga'] === null)));
});
