<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * El papel con que llega una compra. Solo la factura da crédito fiscal: su
 * IGV se recupera y no es costo. En una boleta el IGV sí es parte del costo.
 */
enum TipoComprobante: string
{
    use HasLabel;

    case Factura = 'factura';
    case Boleta = 'boleta';
    case NotaVenta = 'nota_venta';
    case SinComprobante = 'sin_comprobante';

    public function label(): string
    {
        return match ($this) {
            self::Factura => 'Factura',
            self::Boleta => 'Boleta',
            self::NotaVenta => 'Nota de venta',
            self::SinComprobante => 'Sin comprobante',
        };
    }

    public function daCreditoFiscal(): bool
    {
        return $this === self::Factura;
    }

    public function abreviatura(): string
    {
        return match ($this) {
            self::Factura => 'FAC',
            self::Boleta => 'BOL',
            self::NotaVenta => 'NV',
            self::SinComprobante => 'S/C',
        };
    }
}
