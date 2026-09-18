<?php

use App\Enums\MetodoCosto;
use App\Models\ComponenteCosto;
use App\Models\ParametroFlota;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    foreach (['admin', 'visor', 'conductor'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

function tasaCalculada(string $nombre): float
{
    return ComponenteCosto::query()
        ->where('nombre', $nombre)
        ->sole()
        ->tasaCalculada(ParametroFlota::vigentes());
}

it('discounts the lost days from the year to get the sellable ones', function (): void {
    // 365 − 4.87 mantenimiento − 4 certificaciones − 53 sincronización.
    expect(ParametroFlota::vigentes()->diasDisponibles())->toEqualWithDelta(303.13, 0.001);
});

/**
 * Cada uno de estos números sale del Excel «ESTRUCTURA DE COSTOS - SERVICIOS A
 * MINSUR» (agosto 2026). Si el motor se desvía, la tarifa que sale del sistema
 * deja de ser la que se negocia en la calle.
 */
it('derives the driver day cost from the payroll', function (): void {
    expect(tasaCalculada('Mano de obra directa (choferes)'))
        ->toEqualWithDelta(258.944, 0.01);
});

it('derives the fuel cost per km from the price mix and the mileage', function (): void {
    // (0.5×22.30 + 0.2×22.05 + 0.3×22.68) sin IGV ÷ 7.49 km/galón, más la UREA.
    expect(tasaCalculada('Consumo de combustible'))->toEqualWithDelta(2.6321, 0.0001);
});

it('derives the tyre cost from the whole retread cycle', function (): void {
    // 57,697.20 soles ÷ 234,000 km de las tres vidas juntas.
    expect(tasaCalculada('Desgaste y reposición de neumáticos'))->toEqualWithDelta(0.24657, 0.0001);
});

it('spreads an annual amount over the fleet days', function (): void {
    // 1,312,228 × 75% de dedicación ÷ (100 unidades × 303.13 días).
    expect(tasaCalculada('Mano de obra indirecta'))->toEqualWithDelta(32.467, 0.01);
});

it('charges the asset for both depreciation and tied-up capital', function (): void {
    $componente = ComponenteCosto::factory()->conMetodo(MetodoCosto::Activo, [
        'valor_tracto' => 300000,
        'valor_carreta' => 100000,
        'valor_residual_pct' => 0.5,
        'vida_util_anios' => 10,
        'tasa_anual' => 0.10,
    ])->make();

    $flota = ParametroFlota::factory()->make(['dias_ano' => 365, 'dias_mantenimiento' => 0, 'dias_certificaciones' => 0, 'dias_sincronizacion' => 65]);

    // Depreciación 400,000 × 0.5 ÷ 10 = 20,000. Capital 400,000 × 0.75 × 0.10
    // = 30,000. Juntos 50,000 al año, sobre 300 días disponibles.
    expect($componente->tasaCalculada($flota))->toEqualWithDelta(166.667, 0.001);
});

/**
 * La distinción es fácil de perder de vista y cambia el número: en los
 * neumáticos las vidas son sucesivas sobre el mismo juego, y en el
 * mantenimiento cada servicio corre por su cuenta.
 */
it('does not confuse successive lives with recurring services', function (): void {
    $entradas = [['concepto' => 'A', 'costo' => 1000, 'km' => 10000, 'cada_km' => 10000]];

    $flota = ParametroFlota::factory()->make();

    $ciclo = ComponenteCosto::factory()
        ->conMetodo(MetodoCosto::CicloVida, ['vidas' => [...$entradas, ...$entradas]])
        ->make();

    $frecuencia = ComponenteCosto::factory()
        ->conMetodo(MetodoCosto::FrecuenciaKm, ['servicios' => [...$entradas, ...$entradas]])
        ->make();

    // Dos vidas de 1000/10.000 km siguen costando 0.10 por km; dos servicios
    // que se repiten cada 10.000 km cuestan 0.20.
    expect($ciclo->tasaCalculada($flota))->toEqualWithDelta(0.10, 0.0001)
        ->and($frecuencia->tasaCalculada($flota))->toEqualWithDelta(0.20, 0.0001);
});

it('shows the steps that lead to the rate', function (): void {
    $componente = ComponenteCosto::query()->where('nombre', 'Consumo de combustible')->sole();

    $pasos = collect($componente->derivacion(ParametroFlota::vigentes())->pasos);

    expect($pasos->pluck('etiqueta'))
        ->toContain('Precio promedio del galón', 'Precio sin IGV', 'Combustible por km');
});

it('quotes with the written rate, not with the one its calculator suggests', function (): void {
    $componente = ComponenteCosto::query()
        ->where('nombre', 'Mano de obra directa (choferes)')
        ->sole();

    $componente->update(['tasa' => 300]);

    expect($componente->tasa)->toBe(300.0)
        ->and($componente->tasaCalculada(ParametroFlota::vigentes()))->toEqualWithDelta(258.944, 0.01);
});

it('has no calculator behind a written-only line', function (): void {
    expect(ComponenteCosto::factory()->fijoDia(123.45)->make()->tieneCalculadora())->toBeFalse()
        ->and(ComponenteCosto::factory()->conMetodo(MetodoCosto::PlanillaConductor, [])->make()->tieneCalculadora())->toBeTrue();
});

it('survives entries left empty without blowing up the screen', function (): void {
    $componente = ComponenteCosto::factory()
        ->conMetodo(MetodoCosto::PlanillaConductor, [])
        ->make();

    expect($componente->tasaCalculada(ParametroFlota::factory()->make()))->toBe(0.0);
});

it('lets admins edit the entries of a component', function (): void {
    $componente = ComponenteCosto::query()
        ->where('nombre', 'Mano de obra directa (choferes)')
        ->sole();

    actingAs(actorConRol('admin'))
        ->put(route('parametros-costo.componentes.update', $componente), [
            'nombre' => $componente->nombre,
            'tasa' => $componente->tasa,
            'activo' => true,
            'entradas' => [...$componente->entradas, 'sueldo_base' => 5000],
        ])
        ->assertRedirect();

    // La calculadora sugiere otro número, pero la tasa con la que se cotiza
    // no cambia hasta que alguien decida usarlo.
    expect(tasaCalculada('Mano de obra directa (choferes)'))->toBeGreaterThan(300.0)
        ->and($componente->fresh()->tasa)->toBe(258.94);
});

it('rejects entries that would break the derivation', function (): void {
    $componente = ComponenteCosto::query()->where('nombre', 'Consumo de combustible')->sole();

    actingAs(actorConRol('admin'))
        ->put(route('parametros-costo.componentes.update', $componente), [
            'nombre' => $componente->nombre,
            'tasa' => $componente->tasa,
            'activo' => true,
            // Un rendimiento en cero dividiría por cero.
            'entradas' => [...$componente->entradas, 'rendimiento_km_galon' => 0],
        ])
        ->assertSessionHasErrors('entradas.rendimiento_km_galon');
});

it('forbids viewers from touching the structure', function (): void {
    $componente = ComponenteCosto::query()->first();

    actingAs(actorConRol('visor'))
        ->put(route('parametros-costo.componentes.update', $componente), [
            'nombre' => 'Otro',
            'tasa' => 1,
            'activo' => true,
            'entradas' => $componente->entradas,
        ])
        ->assertForbidden();
});
