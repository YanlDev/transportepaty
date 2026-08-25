<?php

use App\Models\Viaje;

/**
 * @param  array<string, mixed>  $overrides
 */
function viajeParaAgrupar(array $overrides = []): Viaje
{
    return new Viaje(array_merge([
        'fecha_traslado' => '2026-08-19',
        'placa_tracto' => 'TCK922',
        'placa_carreta' => 'VDI980',
        'tracto_id' => 10,
        'carreta_id' => 20,
        'conductor_nombre' => 'VILCA CHOQUEHUANCA DAVID',
        'conductor_dni' => '12345678',
        'conductor_id' => 30,
    ], $overrides));
}

it('agrupa dos GR del mismo tracto, carreta, conductor y día bajo la misma clave', function (): void {
    $unaGr = viajeParaAgrupar();
    $otraGr = viajeParaAgrupar();

    expect($unaGr->claveGrupoViaje())->toBe($otraGr->claveGrupoViaje());
});

it('separa el grupo si cambia la fecha, el tracto o el conductor', function (): void {
    $base = viajeParaAgrupar()->claveGrupoViaje();

    expect(viajeParaAgrupar(['fecha_traslado' => '2026-08-20'])->claveGrupoViaje())->not->toBe($base)
        ->and(viajeParaAgrupar(['tracto_id' => 99])->claveGrupoViaje())->not->toBe($base)
        ->and(viajeParaAgrupar(['conductor_id' => 99])->claveGrupoViaje())->not->toBe($base);
});

it('cae a la placa o al DNI cuando tracto, carreta o conductor no matchearon contra el padrón', function (): void {
    $sinPadron = viajeParaAgrupar([
        'tracto_id' => null,
        'carreta_id' => null,
        'conductor_id' => null,
    ]);

    // Sigue agrupando por placa/DNI en vez de tumbarse o quedar sin clave.
    expect($sinPadron->claveGrupoViaje())
        ->toContain('placa:TCK922')
        ->toContain('placa:VDI980')
        ->toContain('dni:12345678');
});

it('marca "sin-carreta" cuando el viaje no trae carreta', function (): void {
    $sinCarreta = viajeParaAgrupar(['placa_carreta' => null, 'carreta_id' => null]);

    expect($sinCarreta->claveGrupoViaje())->toContain('sin-carreta');
});
