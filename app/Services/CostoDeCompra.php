<?php

namespace App\Services;

use App\Enums\Moneda;
use App\Enums\TipoComprobante;

/**
 * Pasa una compra del comprobante al almacén.
 *
 * El comprobante se registra como viene —su moneda, con o sin IGV en los
 * precios— y de cada línea sale lo que entra al kardex: en soles y al costo
 * real para la empresa. Con factura el IGV se recupera como crédito fiscal,
 * así que no es costo; con boleta se paga y no vuelve, así que sí lo es.
 * Nota de venta o sin comprobante: lo pagado, sin IGV aparte.
 *
 * @phpstan-type LineaCompra array{cantidad: float, precio_unitario: float, factor: float}
 * @phpstan-type LineaValorizada array{cantidad_base: float, costo_unitario: float, costo_total: float}
 */
class CostoDeCompra
{
    public const TASA_IGV = 0.18;

    /**
     * @param  list<LineaCompra>  $lineas
     * @return array{subtotal: float, igv: float, total: float, costo_total: float, lineas: list<LineaValorizada>}
     */
    public function calcular(
        TipoComprobante $comprobante,
        Moneda $moneda,
        ?float $tipoCambio,
        bool $preciosConIgv,
        array $lineas,
    ): array {
        $llevaIgv = in_array($comprobante, [TipoComprobante::Factura, TipoComprobante::Boleta], true);
        $cambio = $moneda === Moneda::Dolares ? (float) $tipoCambio : 1.0;

        $suma = 0.0;
        $valorizadas = [];

        foreach ($lineas as $linea) {
            $importe = $linea['cantidad'] * $linea['precio_unitario'];
            $suma += $importe;

            // El importe de la línea al costo de la empresa, en la moneda del
            // comprobante.
            $costo = match (true) {
                ! $llevaIgv => $importe,
                $comprobante->daCreditoFiscal() => $preciosConIgv ? $importe / (1 + self::TASA_IGV) : $importe,
                default => $preciosConIgv ? $importe : $importe * (1 + self::TASA_IGV),
            };

            $cantidadBase = round($linea['cantidad'] * $linea['factor'], 3);
            $costoTotal = round($costo * $cambio, 2);

            $valorizadas[] = [
                'cantidad_base' => $cantidadBase,
                'costo_unitario' => $cantidadBase > 0 ? round($costoTotal / $cantidadBase, 4) : 0.0,
                'costo_total' => $costoTotal,
            ];
        }

        $suma = round($suma, 2);

        [$subtotal, $igv, $total] = match (true) {
            ! $llevaIgv => [$suma, 0.0, $suma],
            $preciosConIgv => [round($suma / (1 + self::TASA_IGV), 2), round($suma - round($suma / (1 + self::TASA_IGV), 2), 2), $suma],
            default => [$suma, round($suma * self::TASA_IGV, 2), round($suma + round($suma * self::TASA_IGV, 2), 2)],
        };

        return [
            'subtotal' => $subtotal,
            'igv' => $igv,
            'total' => $total,
            'costo_total' => round(array_sum(array_column($valorizadas, 'costo_total')), 2),
            'lineas' => $valorizadas,
        ];
    }
}
