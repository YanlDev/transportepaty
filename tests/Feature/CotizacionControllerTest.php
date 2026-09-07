<?php

use App\Models\ComponenteCosto;
use App\Models\Cotizacion;
use App\Models\ParametroFlota;
use App\Services\CalculadoraCotizacion;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    foreach (['admin', 'visor', 'conductor'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function datosCotizacion(array $overrides = []): array
{
    return array_merge([
        'fecha' => now()->toDateString(),
        'valido_hasta' => now()->addDays(15)->toDateString(),
        'cliente_id' => null,
        'cliente_nombre' => 'IMPORTACIONES MELMA S.A.C.',
        'cliente_ruc' => '20600812913',
        'punto_partida_id' => null,
        'punto_llegada_id' => null,
        'origen' => 'JULIACA',
        'destino' => 'AREQUIPA',
        'material' => 'Materiales varios',
        'km' => 300,
        'dias' => 2,
        'peajes' => 150,
        'viaticos' => 100,
        'alojamiento' => 0,
        'cochera' => 0,
        'carga_descarga' => 0,
        'otros_ruta' => 0,
        'margen_pct' => 0.12,
        'estado' => 'borrador',
        'notas' => null,
    ], $overrides);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function datosFlota(array $overrides = []): array
{
    return array_merge([
        'tamano_flota' => 100,
        'dias_ano' => 365,
        'dias_mantenimiento' => 4.87,
        'dias_certificaciones' => 4,
        'dias_sincronizacion' => 53,
        'igv_pct' => 0.18,
        'margen_pct_default' => 0.12,
        'viatico_dia' => 50,
    ], $overrides);
}

it('redirects guests to login', function (): void {
    $this->get(route('cotizaciones.index'))->assertRedirect(route('login'));
});

it('lets admins and viewers see the list', function (): void {
    $cotizacion = Cotizacion::factory()->create();

    actingAs(actorConRol('visor'))
        ->get(route('cotizaciones.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('cotizaciones/index')
            ->has('cotizaciones.data', 1)
            ->where('cotizaciones.data.0.numero', $cotizacion->numero)
        );
});

it('forbids drivers from the list', function (): void {
    actingAs(actorConRol('conductor'))
        ->get(route('cotizaciones.index'))
        ->assertForbidden();
});

it('renders the create, edit and show screens', function (): void {
    $cotizacion = Cotizacion::factory()->create();

    actingAs(actorConRol('admin'))
        ->get(route('cotizaciones.create'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('cotizaciones/create')
            ->has('clientes')
            ->has('puntos')
            ->has('flota')
        );

    actingAs(actorConRol('admin'))
        ->get(route('cotizaciones.edit', $cotizacion))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->component('cotizaciones/edit'));

    actingAs(actorConRol('visor'))
        ->get(route('cotizaciones.show', $cotizacion))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('cotizaciones/show')
            ->where('cotizacion.numero', $cotizacion->numero)
        );

    actingAs(actorConRol('admin'))
        ->get(route('parametros-costo.edit'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('parametros-costo/edit')
            ->has('flota.dias_disponibles')
            ->has('totales.fijo_dia_indirecto')
            // Cada componente llega con su tasa ya resuelta y los pasos que la
            // explican: es lo que la pantalla despliega.
            ->has('componentes.0.tasa')
            ->has('componentes.0.pasos')
            ->has('componentes', 9)
        );
});

it('forbids viewers from creating', function (): void {
    actingAs(actorConRol('visor'))
        ->post(route('cotizaciones.store'), datosCotizacion())
        ->assertForbidden();
});

it('calculates the tariff on the server when storing', function (): void {
    actingAs(actorConRol('admin'))
        ->post(route('cotizaciones.store'), datosCotizacion())
        ->assertRedirect();

    $cotizacion = Cotizacion::query()->sole();

    expect($cotizacion->costo_operativo)
        ->toBe(round($cotizacion->total_directo + $cotizacion->total_indirecto, 2))
        ->and($cotizacion->margen)->toBe(round($cotizacion->costo_operativo * 0.12, 2))
        ->and($cotizacion->igv)->toBe(round($cotizacion->subtotal * 0.18, 2))
        ->and($cotizacion->total)->toBe(round($cotizacion->subtotal + $cotizacion->igv, 2))
        // Los costos del tramo son directos: entran enteros en esa mitad.
        ->and($cotizacion->total_directo)->toBeGreaterThan(250.0);
});

it('ignores any totals sent by the client', function (): void {
    actingAs(actorConRol('admin'))
        ->post(route('cotizaciones.store'), datosCotizacion([
            'total' => 1,
            'subtotal' => 1,
            'costo_operativo' => 1,
        ]))
        ->assertRedirect();

    expect(Cotizacion::query()->sole()->total)->toBeGreaterThan(1000.0);
});

it('numbers quotes correlatively within the series', function (): void {
    actingAs(actorConRol('admin'))->post(route('cotizaciones.store'), datosCotizacion());
    actingAs(actorConRol('admin'))->post(route('cotizaciones.store'), datosCotizacion());

    expect(Cotizacion::query()->orderBy('id')->pluck('numero')->all())
        ->toBe([Cotizacion::SERIE.'-0001', Cotizacion::SERIE.'-0002']);
});

it('never reuses the number of a deleted quote', function (): void {
    Cotizacion::factory()->create(['numero' => Cotizacion::SERIE.'-0007'])->delete();

    expect(Cotizacion::siguienteNumero())->toBe(Cotizacion::SERIE.'-0001');

    Cotizacion::factory()->create(['numero' => Cotizacion::SERIE.'-0007']);

    expect(Cotizacion::siguienteNumero())->toBe(Cotizacion::SERIE.'-0008');
});

it('validates the route data', function (): void {
    actingAs(actorConRol('admin'))
        ->post(route('cotizaciones.store'), datosCotizacion([
            'km' => 0,
            'dias' => 0,
            'cliente_nombre' => '',
        ]))
        ->assertSessionHasErrors(['km', 'dias', 'cliente_nombre']);
});

it('recalculates on update with the rates the quote was issued with', function (): void {
    $cotizacion = Cotizacion::factory()->create(['km' => 300, 'dias' => 2]);
    $lineasOriginales = $cotizacion->lineasCongeladas();

    // El diésel sube después de emitida: corregir el kilometraje no puede
    // arrastrar el precio nuevo a una proforma que el cliente ya tiene.
    ComponenteCosto::query()->where('nombre', 'Combustible')->update([
        'metodo' => 'manual',
        'entradas' => json_encode(['tasa' => 99]),
    ]);

    actingAs(actorConRol('admin'))
        ->put(route('cotizaciones.update', $cotizacion), datosCotizacion(['km' => 400]))
        ->assertRedirect();

    $cotizacion->refresh();

    expect($cotizacion->lineasCongeladas())->toBe($lineasOriginales)
        ->and($cotizacion->km)->toBe(400);
});

it('previews the breakdown without saving anything', function (): void {
    actingAs(actorConRol('admin'))
        ->postJson(route('cotizaciones.previsualizar'), [
            'km' => 300,
            'dias' => 2,
            'peajes' => 250,
            'margen_pct' => 0.12,
        ])
        ->assertSuccessful()
        ->assertJsonStructure([
            'desglose' => ['componentes', 'ruta'],
            'total_directo', 'total_indirecto', 'costo_por_km', 'subtotal', 'igv', 'total',
        ]);

    expect(Cotizacion::query()->count())->toBe(0);
});

it('serves the proforma as a pdf', function (): void {
    $cotizacion = Cotizacion::factory()->create();

    $respuesta = actingAs(actorConRol('admin'))
        ->get(route('cotizaciones.pdf', $cotizacion))
        ->assertSuccessful();

    expect($respuesta->headers->get('content-type'))->toContain('application/pdf');
});

it('lets admins update the cost parameters', function (): void {
    actingAs(actorConRol('admin'))
        ->put(route('parametros-costo.update'), datosFlota(['tamano_flota' => 80]))
        ->assertRedirect();

    expect(ParametroFlota::vigentes()->tamano_flota)->toBe(80);
});

it('forbids viewers from touching the cost parameters', function (): void {
    actingAs(actorConRol('visor'))
        ->put(route('parametros-costo.update'), datosFlota())
        ->assertForbidden();
});

/**
 * El caso real que ya se cotiza en el Excel de la casa: Pisco → San Rafael,
 * 1264 km en 5.5 días. Si el motor nuevo se desvía de este número, la tarifa
 * que sale del sistema deja de ser la que se negocia en la calle.
 */
it('reproduces the cost per km of the real Pisco-San Rafael route', function (): void {
    $flota = ParametroFlota::vigentes();
    $calculadora = new CalculadoraCotizacion;

    $calculo = $calculadora->calcular(
        ['km' => 1264, 'dias' => 5.5, 'peajes' => 218.2, 'viaticos' => 275, 'cochera' => 105.4, 'margen_pct' => 0.12],
        $calculadora->lineasDesde(ComponenteCosto::query()->activos()->ordenados()->get(), $flota),
        $flota->igv_pct,
    );

    $costoPorKm = (new Cotizacion([...$calculo, 'km' => 1264]))->costoPorKm();

    // Fijos ~3754 + variables ~3903 + ruta ~599 → alrededor de 6.5 S/. por km.
    expect($costoPorKm)->toBeGreaterThan(6.2)
        ->and($costoPorKm)->toBeLessThan(6.9);

    // Las participaciones se miden contra el subtotal, así que los costos se
    // reparten todo menos lo que se lleva el margen.
    $participaciones = collect([
        ...$calculo['desglose']['componentes'],
        ...$calculo['desglose']['ruta'],
    ])->sum('participacion_pct');

    expect($participaciones + $calculo['margen'] / $calculo['subtotal'])
        ->toEqualWithDelta(1.0, 0.001);
});
