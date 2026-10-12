<?php

use App\Services\DesgloseFactura;

/**
 * Las cifras tienen que salir idénticas a las de las facturas que imprime
 * SUNAT. Cada caso es una factura real de la empresa.
 */
it('desglosa el flete pactado sin IGV como la factura impresa', function (float $valor, float $igv, float $total, float $detraccion, float $neto): void {
    expect(DesgloseFactura::desdeValor($valor, 0.18, 0.04, 400))->toBe([
        'monto' => $valor,
        'igv' => $igv,
        'total' => $total,
        'detraccion' => $detraccion,
        'neto' => $neto,
    ]);
})->with([
    'E001-14220 Bureau Veritas' => [9200.00, 1656.00, 10856.00, 434.0, 10422.00],
    'E001-14372 Socorro' => [3500.00, 630.00, 4130.00, 165.0, 3965.00],
    'E001-14444 Promart' => [41097.01, 7397.46, 48494.47, 1940.0, 46554.47],
]);

it('desglosa el flete pactado con IGV sin perder un céntimo del total', function (): void {
    // E001-14411, Crisar: se pactaron S/ 8 400 con IGV.
    expect(DesgloseFactura::desdeTotal(8400, 0.18, 0.04, 400))->toBe([
        'monto' => 7118.64,
        'igv' => 1281.36,
        'total' => 8400.0,
        'detraccion' => 336.0,
        'neto' => 8064.0,
    ]);
});

it('no aplica detracción hasta pasar el umbral', function (): void {
    expect(DesgloseFactura::desdeTotal(400, 0.18, 0.04, 400))
        ->detraccion->toBe(0.0)
        ->neto->toBe(400.0)
        ->and(DesgloseFactura::desdeTotal(400.01, 0.18, 0.04, 400))
        ->detraccion->toBe(16.0);
});

it('keeps the céntimos of the detracción when the client does not round it', function (): void {
    // E001-14465, Minsur: 4% de 112 706.20 = 4 508.248 → 4 508.25.
    expect(DesgloseFactura::desdeValor(95513.73, 0.18, 0.04, 400, redondear: false))->toBe([
        'monto' => 95513.73,
        'igv' => 17192.47,
        'total' => 112706.2,
        'detraccion' => 4508.25,
        'neto' => 108197.95,
    ])
        ->and(DesgloseFactura::desdeValor(95513.73, 0.18, 0.04, 400)['detraccion'])->toBe(4508.0);
});
