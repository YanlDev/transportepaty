<?php

use App\Enums\PlantillaVuelta;
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
    'Arequipa' => ['VIA EVITAMIENTO KM 3.9 - CERRO COLORADO - AREQUIPA - AREQUIPA', ZonaRuta::Sur],
    'Cusco' => ['AV. LA CULTURA 100 - WANCHAQ - CUSCO - CUSCO', ZonaRuta::Cusco],
    'kilómetro no es Ilo' => ['KILOMETRO 5 - SANTA ROSA - XX - YY', null],
    'ninguna conocida' => ['CALLE SIN NOMBRE', null],
]);

it('sends whatever ends in the highlands to the outbound leg', function (): void {
    expect(SentidoTramo::hacia(ZonaRuta::Mina))->toBe(SentidoTramo::Ida)
        ->and(SentidoTramo::hacia(ZonaRuta::Juliaca))->toBe(SentidoTramo::Ida)
        ->and(SentidoTramo::hacia(ZonaRuta::Sur))->toBe(SentidoTramo::Ida)
        ->and(SentidoTramo::hacia(ZonaRuta::Pisco))->toBe(SentidoTramo::Regreso)
        ->and(SentidoTramo::hacia(ZonaRuta::Lima))->toBe(SentidoTramo::Regreso);
});

it('starts every template going up and ends it coming down', function (PlantillaVuelta $plantilla): void {
    $paradas = $plantilla->paradas();

    expect($paradas[0]['sentido'])->toBe(SentidoTramo::Ida)
        ->and(end($paradas)['sentido'])->toBe(SentidoTramo::Regreso);

    // Cada parada arranca donde terminó la anterior: la plantilla no salta.
    foreach (array_slice($paradas, 1) as $indice => $parada) {
        expect($parada['zona_origen'])->toBe($paradas[$indice]['zona_destino']);
    }
})->with(PlantillaVuelta::cases());
