<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * En qué punto del cobro está un viaje. No es una columna: se deriva de si el
 * viaje tiene factura y de si esa factura tiene fecha de pago, que son los dos
 * únicos hechos que se registran. Tenerlo como enum evita que cada vista
 * reinvente la misma condición.
 */
enum EstadoCobranza: string
{
    use HasLabel;

    case SinFacturar = 'sin_facturar';
    case Facturado = 'facturado';
    case Pagado = 'pagado';

    public function label(): string
    {
        return match ($this) {
            self::SinFacturar => 'Sin facturar',
            self::Facturado => 'Por cobrar',
            self::Pagado => 'Pagado',
        };
    }
}
