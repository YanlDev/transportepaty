<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Por dónde entra un cobro: una cuenta de banco o la caja, para lo que el
 * cliente paga en efectivo. La caja es una «cuenta» más para que el efectivo
 * se elija de la misma lista y no se mezcle con lo que hay que conciliar
 * contra el extracto de un banco.
 */
enum TipoCuenta: string
{
    use HasLabel;

    case Banco = 'banco';
    case Caja = 'caja';

    public function label(): string
    {
        return match ($this) {
            self::Banco => 'Cuenta de banco',
            self::Caja => 'Caja (efectivo)',
        };
    }
}
