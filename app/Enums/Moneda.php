<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * En qué moneda se emitió la factura. Casi todo se cobra en soles, pero
 * algunos clientes (mineras sobre todo) facturan en dólares y mezclar ambas
 * en un mismo total daría un número sin sentido.
 */
enum Moneda: string
{
    use HasLabel;

    case Soles = 'PEN';
    case Dolares = 'USD';

    public function label(): string
    {
        return match ($this) {
            self::Soles => 'Soles',
            self::Dolares => 'Dólares',
        };
    }

    /**
     * El símbolo con el que se antepone el monto en tablas y totales.
     */
    public function simbolo(): string
    {
        return match ($this) {
            self::Soles => 'S/',
            self::Dolares => '$',
        };
    }
}
