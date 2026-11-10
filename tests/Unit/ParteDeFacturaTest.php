<?php

use App\Services\ParteDeFactura;

it('reparte el monto por GR sin perder un céntimo', function (): void {
    $ids = [7, 3, 5];

    expect(ParteDeFactura::repartir(100.00, $ids, [3]))->toBe(33.34)
        ->and(ParteDeFactura::repartir(100.00, $ids, [5]))->toBe(33.33)
        ->and(ParteDeFactura::repartir(100.00, $ids, [7]))->toBe(33.33)
        ->and(ParteDeFactura::repartir(100.00, $ids, [3, 5]))->toBe(66.67)
        ->and(ParteDeFactura::repartir(100.00, $ids, $ids))->toBe(100.00);
});

it('hace que las partes sumen el monto, se corte por donde se corte', function (string $monto, int $cantidad): void {
    $ids = range(1, $cantidad);

    foreach (range(0, $cantidad) as $corte) {
        $centimos = (int) round(ParteDeFactura::repartir($monto, $ids, array_slice($ids, 0, $corte)) * 100)
            + (int) round(ParteDeFactura::repartir($monto, $ids, array_slice($ids, $corte)) * 100);

        expect($centimos)->toBe((int) round((float) $monto * 100));
    }
})->with([
    ['95366.79', 11],
    ['48612.47', 6],
    ['11.85', 3],
    ['0.01', 4],
]);

it('no aporta nada sin GR dentro ni sin monto', function (): void {
    expect(ParteDeFactura::repartir(500.00, [1, 2], []))->toBe(0.0)
        ->and(ParteDeFactura::repartir(null, [1, 2], [1]))->toBe(0.0);
});
