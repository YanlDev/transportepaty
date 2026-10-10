<?php

namespace App\Services;

/**
 * Las cifras de una factura de flete a partir de la que se pactó: el valor
 * (sin IGV) o el total (con IGV). Es la misma cuenta que imprime SUNAT:
 *
 * - IGV y total salen del valor; si lo pactado fue el total, el valor sale
 *   dividiendo y el IGV es la diferencia, para que el total quede exacto
 *   (8 400.00 → 7 118.64 + 1 281.36, no 8 399.99).
 * - La detracción es un porcentaje del total redondeado a soles enteros, como
 *   la deposita el cliente; por debajo del umbral no hay detracción.
 * - El neto es lo que el cliente deposita en la cuenta de la empresa: el
 *   total menos la detracción.
 *
 * Las tasas llegan como fracción (0.18, 0.04), igual que en la configuración.
 */
final class DesgloseFactura
{
    /**
     * @return array{monto: float, igv: float, total: float, detraccion: float, neto: float}
     */
    public static function desdeValor(float $valor, float $tasaIgv, float $tasaDetraccion, float $umbral): array
    {
        $valor = round($valor, 2);
        $igv = round($valor * $tasaIgv, 2);

        return self::completar($valor, $igv, round($valor + $igv, 2), $tasaDetraccion, $umbral);
    }

    /**
     * @return array{monto: float, igv: float, total: float, detraccion: float, neto: float}
     */
    public static function desdeTotal(float $total, float $tasaIgv, float $tasaDetraccion, float $umbral): array
    {
        $total = round($total, 2);
        $valor = round($total / (1 + $tasaIgv), 2);

        return self::completar($valor, round($total - $valor, 2), $total, $tasaDetraccion, $umbral);
    }

    /**
     * @return array{monto: float, igv: float, total: float, detraccion: float, neto: float}
     */
    private static function completar(float $valor, float $igv, float $total, float $tasaDetraccion, float $umbral): array
    {
        $detraccion = $total > $umbral ? round($total * $tasaDetraccion) : 0.0;

        return [
            'monto' => $valor,
            'igv' => $igv,
            'total' => $total,
            'detraccion' => $detraccion,
            'neto' => round($total - $detraccion, 2),
        ];
    }
}
