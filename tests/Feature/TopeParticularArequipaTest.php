<?php

use App\Enums\SentidoTramo;
use App\Models\Abastecimiento;
use App\Models\LineaRuta;
use App\Models\PuntoAbastecimiento;
use App\Models\Ruta;
use App\Models\RutaVuelta;
use App\Models\Vuelta;
use App\Services\GastosDelTicket;

/**
 * «Particular a Arequipa» como estaba en prod: el full en Lima y la línea de
 * Arequipa sin galones, así el tramo quedaba sin tope.
 */
function rompeParticularArequipa(): void
{
    // Antes de los galones por tramo: las rutas con full tenían su total a mano.
    Ruta::query()->whereIn('nombre', ['Particular a Arequipa', 'Particular a Juliaca'])->update(['por_tramos' => false]);
    $ruta = rutaDelCatalogo('Particular a Arequipa');
    $ruta->lineas()->where('tipo', 'combustible')->delete();
    $lima = PuntoAbastecimiento::query()->where('nombre', 'Lima')->value('id');
    $arequipa = PuntoAbastecimiento::query()->where('nombre', 'Arequipa')->value('id');

    LineaRuta::query()->create(['ruta_id' => $ruta->id, 'tipo' => 'combustible', 'concepto' => 'Combustible', 'punto_abastecimiento_id' => $lima, 'galones' => null, 'tanque_lleno' => true, 'orden' => 1]);
    LineaRuta::query()->create(['ruta_id' => $ruta->id, 'tipo' => 'combustible', 'concepto' => 'Combustible', 'punto_abastecimiento_id' => $arequipa, 'galones' => null, 'tanque_lleno' => false, 'orden' => 2]);
}

/** Un ticket que arranca en Lima con esa ruta en la vuelta y los complementos. */
function ticketDeAlca(string ...$complementos): Vuelta
{
    $vuelta = Vuelta::factory()->programada()->create(['inicio' => now()->toDateString()]);

    foreach (['Particular a Arequipa', ...$complementos] as $orden => $nombre) {
        $ruta = rutaDelCatalogo($nombre)->load(['lineas.punto', 'variantes']);
        $vuelta->rutas()->create([...RutaVuelta::copiaDe($ruta, null), 'sentido' => SentidoTramo::Regreso, 'orden' => $orden + 1]);
    }

    return $vuelta;
}

/** @return array{previsto: float|null, registrado: float, falta: float|null} */
function vueltaDe(Vuelta $vuelta): array
{
    $vuelta->refresh()->load(['rutas', 'paradas.viajes']);

    return collect(app(GastosDelTicket::class)->de(collect([$vuelta]))[$vuelta->id]['tramos'])->firstWhere('sentido', 'regreso');
}

function corregirParticularArequipa(): void
{
    (require database_path('migrations/2026_11_11_180342_corrige_el_tope_de_particular_a_arequipa.php'))->up();
}

function pasarRutasConFullAHastaJuliaca(): void
{
    (require database_path('migrations/2026_11_11_195026_pasa_las_rutas_con_full_a_hasta_juliaca.php'))->up();
}

it('gives Particular a Arequipa a 190 gal tope that the Kio full tank deducts from', function (): void {
    rompeParticularArequipa();
    $alca = ticketDeAlca('Ida y vuelta Huaral', 'Uraca (Corire)');
    expect(vueltaDe($alca)['previsto'])->toBeNull();

    corregirParticularArequipa();

    $ruta = rutaDelCatalogo('Particular a Arequipa')->load('lineas.punto');
    expect($ruta->lineas->firstWhere('tanque_lleno', true)->punto->nombre)->toBe('Kio')
        ->and($ruta->topePara(null))->toBe(190.0);

    // El ticket sin combustible toma la ruta corregida: 190 + 20 + 40.
    expect(vueltaDe($alca)['previsto'])->toBe(250.0);

    // Lima 20 gal por Huaral; luego el full de Kio, que con su voucher de
    // 144 gal descuenta del tope: quedan 46 de la ruta y 40 de Corire.
    Abastecimiento::factory()->paraVuelta($alca, SentidoTramo::Regreso)->create(['galones' => 20, 'tanque_lleno' => false]);
    $full = Abastecimiento::factory()->paraVuelta($alca, SentidoTramo::Regreso)->create(['galones' => 0, 'tanque_lleno' => true]);
    $full->update(['galones_despachados' => 144, 'despachado_el' => now()]);

    expect(vueltaDe($alca))->toMatchArray(['previsto' => 250.0, 'registrado' => 164.0, 'falta' => 86.0]);
});

it('leaves alone the tickets that already have fuel in that leg', function (): void {
    rompeParticularArequipa();
    $conCombustible = ticketDeAlca();
    Abastecimiento::factory()->paraVuelta($conCombustible, SentidoTramo::Regreso)->create(['galones' => 20, 'tanque_lleno' => false]);
    $antes = $conCombustible->rutas()->first()->lineas;

    corregirParticularArequipa();

    expect($conCombustible->rutas()->first()->lineas)->toBe($antes);
});

it('says the full-tank routes tope as «Hasta Juliaca», without a grifo', function (): void {
    rompeParticularArequipa();
    $alca = ticketDeAlca('Ida y vuelta Huaral', 'Uraca (Corire)');

    corregirParticularArequipa();
    pasarRutasConFullAHastaJuliaca();

    foreach (['Particular a Juliaca' => 190.0, 'Particular a Arequipa' => 190.0, 'Particular a Cusco' => 200.0, 'Particular a Moquegua – Ilo' => 190.0] as $nombre => $tope) {
        $ruta = rutaDelCatalogo($nombre)->load('lineas.punto');
        $combustible = $ruta->lineas->where('tipo.value', 'combustible');

        expect($ruta->topePara(null))->toBe($tope)
            ->and($combustible->firstWhere('tanque_lleno', true)?->punto?->nombre)->toBe('Kio')
            ->and($combustible->where('tanque_lleno', false)->map(fn ($linea) => [$linea->concepto, $linea->punto_abastecimiento_id])->values()->all())
            ->toBe([['Hasta Juliaca', null]]);
    }

    // El ticket de Alca: mismo tope, y la fila sin grifo dice qué es.
    Abastecimiento::factory()->paraVuelta($alca, SentidoTramo::Regreso)->create(['galones' => 20, 'tanque_lleno' => false, 'punto_abastecimiento_id' => PuntoAbastecimiento::query()->where('nombre', 'Lima')->value('id')]);
    $full = Abastecimiento::factory()->paraVuelta($alca, SentidoTramo::Regreso)->create(['galones' => 0, 'tanque_lleno' => true]);
    $full->update(['galones_despachados' => 144, 'despachado_el' => now()]);

    $tramo = vueltaDe($alca);
    expect($tramo)->toMatchArray(['previsto' => 250.0, 'registrado' => 164.0, 'falta' => 86.0])
        ->and(collect($tramo['puntos'])->pluck('punto')->all())->toContain('Hasta Juliaca + Uraca', 'Lima');
});

it('drops the old «falta poner los galones» note from the full-tank routes', function (): void {
    $ruta = rutaDelCatalogo('Particular a Cusco');
    $ruta->update(['nota' => "La pizarra no tiene el monto de viáticos.\nTenía «tanque full»: falta poner los galones de esa línea."]);

    (require database_path('migrations/2026_11_11_215441_quita_el_aviso_viejo_de_tanque_full.php'))->up();

    expect($ruta->fresh()->nota)->toBe('La pizarra no tiene el monto de viáticos.');
});
