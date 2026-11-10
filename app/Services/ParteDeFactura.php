<?php

namespace App\Services;

use App\Models\Factura;

/**
 * Lo que una factura aporta a un recorte de la cobranza (un mes, un rango de
 * fechas, un estado): la parte que les toca a sus GR que caen dentro.
 *
 * Una factura de 27 GR con 20 en setiembre y 7 en octubre es una sola plata:
 * contarla entera en cada mes la suma dos veces. Acá se reparte por GR, en
 * partes iguales, y setiembre se lleva 20/27. Es una aproximación —no todas
 * las GR valen lo mismo—, pero los meses vuelven a sumar el total.
 *
 * El reparto se hace en céntimos y lo que sobra de la división va a las
 * primeras GR (por id): así las partes de una factura suman exactamente su
 * monto, se corte por donde se corte. 100.00 entre 3 da 33.34 + 33.33 + 33.33.
 */
final readonly class ParteDeFactura
{
    /**
     * @param  list<int>  $viajeIds  Todas las GR de la factura.
     * @param  list<int>  $dentro  Las que caen en el recorte.
     */
    public function __construct(
        public Factura $factura,
        public array $viajeIds,
        public array $dentro,
    ) {}

    /** Todas sus GR están en el recorte: la factura cuenta entera. */
    public function completa(): bool
    {
        return array_diff($this->viajeIds, $this->dentro) === [];
    }

    /** La parte de un monto de la factura que cae en el recorte. */
    public function de(string|float|null $monto): float
    {
        return self::repartir($monto, $this->viajeIds, $this->dentro);
    }

    /**
     * La parte de `$monto` que les toca a las GR `$dentro`, de entre todas
     * las de la factura.
     *
     * @param  array<int, int>  $viajeIds
     * @param  array<int, int>  $dentro
     */
    public static function repartir(string|float|null $monto, array $viajeIds, array $dentro): float
    {
        if ($monto === null) {
            return 0.0;
        }

        $ids = array_values(array_unique($viajeIds));
        sort($ids);

        if ($ids === []) {
            return round((float) $monto, 2);
        }

        $centimos = (int) round((float) $monto * 100);
        $cantidad = count($ids);
        $base = intdiv($centimos, $cantidad);
        $sobrante = $centimos % $cantidad;
        $parte = 0;

        foreach ($ids as $posicion => $id) {
            if (in_array($id, $dentro, true)) {
                $parte += $base + ($posicion < $sobrante ? 1 : 0);
            }
        }

        return $parte / 100;
    }
}
