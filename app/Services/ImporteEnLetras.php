<?php

namespace App\Services;

use NumberFormatter;
use RuntimeException;

/**
 * El importe como se escribe en un comprobante peruano: la parte entera en
 * letras y los céntimos en fracción, «QUINCE MIL TRESCIENTOS NOVENTA Y NUEVE
 * CON 00/100 SOLES».
 */
class ImporteEnLetras
{
    public static function soles(float $monto): string
    {
        $centimos = (int) round($monto * 100);
        $entero = intdiv($centimos, 100);
        $fraccion = $centimos % 100;

        $letras = (new NumberFormatter('es', NumberFormatter::SPELLOUT))->format($entero);

        if ($letras === false) {
            throw new RuntimeException("No se pudo escribir {$monto} en letras.");
        }

        return mb_strtoupper($letras).' CON '.str_pad((string) $fraccion, 2, '0', STR_PAD_LEFT).'/100 SOLES';
    }
}
