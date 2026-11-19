<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Por qué entra algo al almacén: se compró o volvió de una entrega.
 * El saldo inicial y los sobrantes entran por un conteo, no por un ingreso.
 */
enum TipoIngreso: string
{
    use HasLabel;

    case Compra = 'compra';
    case Devolucion = 'devolucion';

    public function label(): string
    {
        return match ($this) {
            self::Compra => 'Recepción de compra',
            self::Devolucion => 'Devolución',
        };
    }
}
