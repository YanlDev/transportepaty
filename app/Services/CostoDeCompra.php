<?php

namespace App\Services;

use App\Enums\Moneda;
use App\Enums\TipoComprobante;

/**
 * Pasa una compra del comprobante al almacén.
 *
 * El comprobante se registra como viene —su moneda, con o sin IGV en los
 * precios— y de cada línea sale lo que entra al kardex: en soles y al costo
 * real para la empresa.
 *
 * - Con factura el IGV es crédito fiscal: no es costo. Con boleta se paga y
 *   no vuelve: sí lo es. Nota de venta, ticket o vale no separan IGV.
 * - El descuento y el flete (o pasajes, encomienda) del comprobante se
 *   reparten entre las líneas pagadas según lo que vale cada una: el filtro
 *   que cuesta el doble carga el doble del flete.
 * - Una bonificación entra con el valor que se le pone, sin IGV, pero no
 *   suma al comprobante: no se pagó.
 * - En dólares, todo pasa a soles con el tipo de cambio del comprobante.
 *
 * El costo de las líneas pagadas suma exactamente el costo del comprobante:
 * el céntimo que sobra al redondear va a la línea más grande.
 *
 * @phpstan-type LineaCompra array{cantidad: float, precio_unitario: float, factor: float, bonificacion?: bool}
 * @phpstan-type LineaValorizada array{cantidad_base: float, costo_unitario: float, costo_total: float}
 */
class CostoDeCompra
{
    public const TASA_IGV = 0.18;

    /**
     * @param  list<LineaCompra>  $lineas
     * @return array{suma_items: float, subtotal: float, igv: float, total: float, costo_total: float, lineas: list<LineaValorizada>}
     */
    public function calcular(
        TipoComprobante $comprobante,
        Moneda $moneda,
        ?float $tipoCambio,
        bool $preciosConIgv,
        array $lineas,
        float $descuento = 0.0,
        float $flete = 0.0,
    ): array {
        $cambio = $moneda === Moneda::Dolares ? (float) $tipoCambio : 1.0;

        $importes = array_map(
            fn (array $linea): float => $linea['cantidad'] * $linea['precio_unitario'],
            $lineas,
        );
        $pagadas = array_keys(array_filter($lineas, fn (array $linea): bool => ! ($linea['bonificacion'] ?? false)));

        $sumaItems = round(array_sum(array_map(fn (int $i): float => $importes[$i], $pagadas)), 2);
        // Lo que dice el comprobante antes de separar el IGV, en la misma base
        // que los precios (con o sin IGV).
        $base = round($sumaItems - $descuento + $flete, 2);

        [$subtotal, $igv, $total] = match (true) {
            ! $comprobante->llevaIgv() => [$base, 0.0, $base],
            $preciosConIgv => [round($base / (1 + self::TASA_IGV), 2), round($base - round($base / (1 + self::TASA_IGV), 2), 2), $base],
            default => [$base, round($base * self::TASA_IGV, 2), round($base + round($base * self::TASA_IGV, 2), 2)],
        };

        // Lo que de verdad le cuesta a la empresa, ya en soles.
        $costoPagado = round(match (true) {
            $comprobante->daCreditoFiscal() => $subtotal,
            default => $total,
        } * $cambio, 2);

        $costos = [];

        foreach ($lineas as $i => $linea) {
            $costos[$i] = ($linea['bonificacion'] ?? false)
                ? round($importes[$i] * $cambio, 2)
                : ($sumaItems > 0 ? round($costoPagado * $importes[$i] / $sumaItems, 2) : 0.0);
        }

        // El céntimo del redondeo va a la línea pagada más grande.
        if ($pagadas !== []) {
            $mayor = $pagadas[0];

            foreach ($pagadas as $i) {
                if ($importes[$i] > $importes[$mayor]) {
                    $mayor = $i;
                }
            }

            $resto = round($costoPagado - array_sum(array_map(fn (int $i): float => $costos[$i], $pagadas)), 2);
            $costos[$mayor] = round($costos[$mayor] + $resto, 2);
        }

        $valorizadas = [];

        foreach ($lineas as $i => $linea) {
            $cantidadBase = round($linea['cantidad'] * $linea['factor'], 3);

            $valorizadas[] = [
                'cantidad_base' => $cantidadBase,
                'costo_unitario' => $cantidadBase > 0 ? round($costos[$i] / $cantidadBase, 4) : 0.0,
                'costo_total' => $costos[$i],
            ];
        }

        return [
            'suma_items' => $sumaItems,
            'subtotal' => $subtotal,
            'igv' => $igv,
            'total' => $total,
            'costo_total' => round(array_sum($costos), 2),
            'lineas' => $valorizadas,
        ];
    }
}
