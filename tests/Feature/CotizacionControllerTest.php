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
            ->has('lineas', 10)
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
            ->has('totales.fijo_dia')
            // Cada línea llega con la tasa con la que se cotiza y, aparte, la
            // que sugiere su calculadora con los pasos que la explican.
            ->has('componentes.0.tasa')
            ->has('componentes.0.tasa_calculada')
            ->has('componentes.0.pasos')
            ->has('componentes', 10)
        );
});

it('opens the quick route sheet with the current rate card', function (): void {
    actingAs(actorConRol('admin'))
        ->get(route('cotizaciones.cotizador'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('cotizaciones/cotizador')
            ->has('lineas', 10)
            ->where('lineas.0.tasa', 268.13)
            ->where('margen_pct_default', 0.12)
            ->where('igv_pct', 0.18)
        );
});

it('keeps the quick route sheet for the ones who quote', function (): void {
    actingAs(actorConRol('visor'))
        ->get(route('cotizaciones.cotizador'))
        ->assertForbidden();
});

it('prefills a new quote with the route worked out in the sheet', function (): void {
    actingAs(actorConRol('admin'))
        ->get(route('cotizaciones.create', [
            'cliente_nombre' => 'CALCESUR',
            'destino' => 'CHILCANO',
            'km' => '1275',
            'dias' => '9',
            'margen_pct' => '0.12',
            'numero' => '006-9999',
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('borrador.km', '1275')
            ->where('borrador.destino', 'CHILCANO')
            ->missing('borrador.numero')
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
        ->toBe(round($cotizacion->total_fijo + $cotizacion->total_variable, 2))
        // Margen sobre el precio de venta: el costo es el 88 % de la tarifa.
        ->and($cotizacion->subtotal)->toBe(round($cotizacion->costo_operativo / 0.88, 2))
        ->and($cotizacion->margen)->toBe(round($cotizacion->subtotal - $cotizacion->costo_operativo, 2))
        ->and($cotizacion->igv)->toBe(round($cotizacion->subtotal * 0.18, 2))
        ->and($cotizacion->total)->toBe(round($cotizacion->subtotal + $cotizacion->igv, 2))
        // 2 días a 648.77 del tarifario de la hoja.
        ->and($cotizacion->total_fijo)->toBe(1297.54);
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
    ComponenteCosto::query()->where('nombre', 'Consumo de combustible')->update(['tasa' => 99]);

    actingAs(actorConRol('admin'))
        ->put(route('cotizaciones.update', $cotizacion), datosCotizacion(['km' => 400]))
        ->assertRedirect();

    $cotizacion->refresh();

    expect($cotizacion->lineasCongeladas())->toBe($lineasOriginales)
        ->and($cotizacion->km)->toBe(400);
});

it('rejects a margin that leaves no possible price', function (): void {
    actingAs(actorConRol('admin'))
        ->post(route('cotizaciones.store'), datosCotizacion(['margen_pct' => 1]))
        ->assertSessionHasErrors('margen_pct');
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
 * La hoja real con la que se cotiza: CALCESUR → Chilcano, cal viva en bigbag,
 * 9 días y 1275 km. Si el sistema se desvía de estos números, la tarifa que
 * sale de acá deja de ser la que se negocia en la calle.
 */
it('reproduces the fixed and variable costs of the real route sheet', function (): void {
    $calculadora = new CalculadoraCotizacion;

    $calculo = $calculadora->calcular(
        ['km' => 1275, 'dias' => 9, 'margen_pct' => 0.12],
        $calculadora->lineasDesde(ComponenteCosto::query()->activos()->ordenados()->get()),
        0.18,
    );

    $importes = collect($calculo['desglose']['componentes'])->pluck('importe', 'nombre');

    expect($calculo['total_fijo'])->toBe(5838.93)
        ->and($importes['Costo de oportunidad de los activos (tracto + carreta)'])->toBe(2413.17)
        ->and($importes['Mano de obra directa (choferes)'])->toBe(2330.46)
        ->and($importes['Consumo de combustible'])->toBe(3213.0)
        ->and($importes['Mantenimiento de vehículos'])->toBe(306.0)
        ->and($importes['Desgaste y reposición de neumáticos'])->toBe(267.75);
});

/**
 * En la hoja, 10,370.68 de costo operativo con 12 % de margen da 11,784.86: el
 * margen es el 12 % de la tarifa, no del costo.
 */
it('takes the margin over the selling price like the route sheet', function (): void {
    $calculo = (new CalculadoraCotizacion)->calcular(
        ['km' => 1, 'dias' => 1, 'margen_pct' => 0.12],
        [['nombre' => 'Costo operativo', 'tipo' => 'fijo_dia', 'naturaleza' => 'directo', 'tasa' => 10370.68]],
        0.18,
    );

    expect($calculo['subtotal'])->toBe(11784.86)
        ->and($calculo['margen'])->toBe(1414.18)
        ->and($calculo['igv'])->toBe(2121.27)
        ->and($calculo['total'])->toBe(13906.13);
});
