<?php

use App\Enums\SentidoTramo;
use App\Enums\ZonaRuta;

/**
 * Direcciones tal como vienen en las GR reales.
 */
it('reads the circuit zone from a GR address', function (string $direccion, ?ZonaRuta $zona): void {
    expect(ZonaRuta::desdeDireccion($direccion))->toBe($zona);
})->with([
    'la mina, aunque diga Juliaca' => ['CAR. DESVIO C.JULIACA-MACUSANI KM. 102 ASIENTO MINERO SAN RAFAEL KM.102.6 PUNO-MELGAR-ANTAUTA MELGAR ANTAUTA Puno - ANTAUTA - MELGAR - PUNO', ZonaRuta::Mina],
    'Paracas' => ['CAR. PANAMERICANA SUR KM 245 - PARACAS - PISCO - ICA', ZonaRuta::Pisco],
    'Callao' => ['AV. ELMER FAUCETT N 3348 Lima y Callao - CALLAO - PROV. CONST. DEL CALLAO - PROV. CONST. DEL CALLAO', ZonaRuta::Lima],
    'Villa El Salvador sin decir Lima' => ['TABLADA DE LURN NRO 224 -MZ. M1 LOTE 4-B- VILLA EL SALVADOR - VILLA EL', ZonaRuta::Lima],
    'Huaral' => ['PARCELA NRO 55 ZONA CAMPO GRAN - HUARAL - HUARAL - LIMA', ZonaRuta::Lima],
    'Juliaca' => ['JR. 8 NOVIEMBRE NRO. 615 OTR. CERCADO - JULIACA - SAN ROMAN - PUNO', ZonaRuta::Juliaca],
    'Arequipa' => ['VIA EVITAMIENTO KM 3.9 - CERRO COLORADO - AREQUIPA - AREQUIPA', ZonaRuta::Arequipa],
    'Ilo, en Moquegua' => ['CAL. MOQUEGUA 120 - ILO - ILO - MOQUEGUA', ZonaRuta::Moquegua],
    'Tacna' => ['CAR. TACNA ALTO PERU KM 88 - PALCA - TACNA - TACNA', ZonaRuta::Tacna],
    'Cusco' => ['AV. LA CULTURA 100 - WANCHAQ - CUSCO - CUSCO', ZonaRuta::Cusco],
    'kilómetro no es Ilo' => ['KILOMETRO 5 - SANTA ROSA - XX - YY', null],
    'ninguna conocida' => ['CALLE SIN NOMBRE', null],
]);

/**
 * La ida sale de Juliaca o de la mina y baja a la costa; el regreso sube.
 */
it('sends whatever ends on the coast to the outbound leg', function (): void {
    expect(SentidoTramo::hacia(ZonaRuta::Pisco))->toBe(SentidoTramo::Ida)
        ->and(SentidoTramo::hacia(ZonaRuta::Lima))->toBe(SentidoTramo::Ida)
        ->and(SentidoTramo::hacia(ZonaRuta::Mina))->toBe(SentidoTramo::Regreso)
        ->and(SentidoTramo::hacia(ZonaRuta::Juliaca))->toBe(SentidoTramo::Regreso)
        ->and(SentidoTramo::hacia(ZonaRuta::Arequipa))->toBe(SentidoTramo::Regreso);
});

/**
 * La vuelta empieza al salir de la sierra (ahí se abastece) y termina al
 * volver a ella.
 */
it('has Juliaca as the only base: there the fuel starts over', function (): void {
    expect(collect(ZonaRuta::cases())->filter(fn (ZonaRuta $zona): bool => $zona->esBase())->values()->all())
        ->toBe([ZonaRuta::Juliaca]);
});

it('places each leg of the circuit in its part of the trip', function (?ZonaRuta $origen, ?ZonaRuta $destino, bool $yaRegresando, SentidoTramo $sentido): void {
    expect(SentidoTramo::entre($origen, $destino, $yaRegresando))->toBe($sentido);
})->with([
    'Juliaca ⇢ mina, vacía' => [ZonaRuta::Juliaca, ZonaRuta::Mina, false, SentidoTramo::Ida],
    'mina → Pisco, concentrado' => [ZonaRuta::Mina, ZonaRuta::Pisco, false, SentidoTramo::Ida],
    'Pisco → Callao, metálico' => [ZonaRuta::Pisco, ZonaRuta::Lima, false, SentidoTramo::Ida],
    'Lima → Juliaca, particular' => [ZonaRuta::Lima, ZonaRuta::Juliaca, false, SentidoTramo::Regreso],
    'Pisco → mina, escoria' => [ZonaRuta::Pisco, ZonaRuta::Mina, false, SentidoTramo::Regreso],
    'Callao → Lima ya regresando' => [ZonaRuta::Lima, ZonaRuta::Lima, true, SentidoTramo::Regreso],
    'Lima → Tacna, particular' => [ZonaRuta::Lima, ZonaRuta::Tacna, false, SentidoTramo::Regreso],
    'Tacna ⇢ Juliaca: sigue el regreso' => [ZonaRuta::Tacna, ZonaRuta::Juliaca, true, SentidoTramo::Regreso],
    'Cusco ⇢ mina: sigue el regreso' => [ZonaRuta::Cusco, ZonaRuta::Mina, true, SentidoTramo::Regreso],
    'mina → Juliaca: vuelve a la base' => [ZonaRuta::Mina, ZonaRuta::Juliaca, false, SentidoTramo::Regreso],
    'Juliaca → mina con escoria: arranca la ida' => [ZonaRuta::Juliaca, ZonaRuta::Mina, true, SentidoTramo::Ida],
    'Tacna → Ilo: dentro del sur' => [ZonaRuta::Tacna, ZonaRuta::Moquegua, true, SentidoTramo::Regreso],
    'Arequipa → Lima: baja' => [ZonaRuta::Arequipa, ZonaRuta::Lima, true, SentidoTramo::Ida],
]);

it('knows how long each route usually takes', function (ZonaRuta $origen, ZonaRuta $destino, int $dias): void {
    expect(ZonaRuta::diasDeViaje($origen, $destino))->toBe($dias);
})->with([
    'concentrado de la mina a Pisco' => [ZonaRuta::Mina, ZonaRuta::Pisco, 3],
    'regreso de Lima a Juliaca' => [ZonaRuta::Lima, ZonaRuta::Juliaca, 3],
    'metálico de Pisco al Callao' => [ZonaRuta::Pisco, ZonaRuta::Lima, 1],
    'subida de Juliaca a la mina' => [ZonaRuta::Juliaca, ZonaRuta::Mina, 1],
    'particular de Lima a Arequipa' => [ZonaRuta::Lima, ZonaRuta::Arequipa, 2],
    'de Arequipa a Juliaca' => [ZonaRuta::Arequipa, ZonaRuta::Juliaca, 1],
    'de Tacna a Cusco' => [ZonaRuta::Tacna, ZonaRuta::Cusco, 2],
    'de Tacna a Ilo' => [ZonaRuta::Tacna, ZonaRuta::Moquegua, 1],
]);
