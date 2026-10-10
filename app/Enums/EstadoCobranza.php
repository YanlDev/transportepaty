<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * En qué punto del cobro está un viaje. No es una columna: se deriva de si el
 * viaje tiene factura y de si ya se cobraron sus dos partes —el neto y la
 * detracción—, más la marca de «no se factura» de las GR que se decidió no
 * cobrar. Tenerlo como enum evita que cada vista reinvente la misma condición.
 */
enum EstadoCobranza: string
{
    use HasLabel;

    case SinFacturar = 'sin_facturar';
    case Facturado = 'facturado';
    case FaltaDetraccion = 'falta_detraccion';
    case Pagado = 'pagado';
    case NoFacturable = 'no_facturable';

    public function label(): string
    {
        return match ($this) {
            self::SinFacturar => 'Sin facturar',
            self::Facturado => 'Por cobrar',
            self::FaltaDetraccion => 'Falta detracción',
            self::Pagado => 'Pagado',
            self::NoFacturable => 'No se factura',
        };
    }
}
