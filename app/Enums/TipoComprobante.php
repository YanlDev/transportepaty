<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * El papel con que llega una compra. Toda compra trae uno —aunque sea un vale
 * o un ticket—, y su foto o PDF se adjunta siempre.
 *
 * Solo la factura da crédito fiscal: su IGV se recupera y no es costo. En una
 * boleta el IGV se paga y no vuelve, así que es parte del costo. El resto
 * (nota de venta, ticket, vale) no separa IGV: el costo es lo pagado.
 */
enum TipoComprobante: string
{
    use HasLabel;

    case Factura = 'factura';
    case Boleta = 'boleta';
    case NotaVenta = 'nota_venta';
    case Ticket = 'ticket';
    case Vale = 'vale';
    case Otro = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::Factura => 'Factura',
            self::Boleta => 'Boleta',
            self::NotaVenta => 'Nota de venta',
            self::Ticket => 'Ticket',
            self::Vale => 'Vale o recibo',
            self::Otro => 'Otro',
        };
    }

    public function daCreditoFiscal(): bool
    {
        return $this === self::Factura;
    }

    /**
     * Si el comprobante separa el IGV: la factura y la boleta.
     */
    public function llevaIgv(): bool
    {
        return in_array($this, [self::Factura, self::Boleta], true);
    }

    /**
     * Factura y boleta son electrónicas: siempre tienen serie y número, y es
     * lo que permite reconocer una repetida.
     */
    public function exigeSerieNumero(): bool
    {
        return $this->llevaIgv();
    }

    public function abreviatura(): string
    {
        return match ($this) {
            self::Factura => 'FAC',
            self::Boleta => 'BOL',
            self::NotaVenta => 'NV',
            self::Ticket => 'TCK',
            self::Vale => 'VALE',
            self::Otro => 'DOC',
        };
    }
}
