<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Por dónde salió el dinero. Todo menos el efectivo deja un número de
 * operación y un voucher.
 */
enum MedioPago: string
{
    use HasLabel;

    case Transferencia = 'transferencia';
    case Deposito = 'deposito';
    case Billetera = 'billetera';
    case Cheque = 'cheque';
    case Efectivo = 'efectivo';

    public function label(): string
    {
        return match ($this) {
            self::Transferencia => 'Transferencia',
            self::Deposito => 'Depósito',
            self::Billetera => 'Yape o Plin',
            self::Cheque => 'Cheque',
            self::Efectivo => 'Efectivo',
        };
    }

    /** El efectivo no pasa por el banco: no tiene operación ni voucher. */
    public function dejaVoucher(): bool
    {
        return $this !== self::Efectivo;
    }
}
