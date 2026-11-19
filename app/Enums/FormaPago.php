<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Cómo se le paga al proveedor: al contado (antes de recoger o contra
 * entrega) o a crédito, a tantos días.
 */
enum FormaPago: string
{
    use HasLabel;

    case Contado = 'contado';
    case Credito = 'credito';

    public function label(): string
    {
        return match ($this) {
            self::Contado => 'Contado',
            self::Credito => 'Crédito',
        };
    }
}
