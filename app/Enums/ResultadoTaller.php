<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Lo que dice taller de la unidad antes de salir. Sin el apto el ticket no
 * avanza: abastecimiento no puede registrar nada.
 */
enum ResultadoTaller: string
{
    use HasLabel;

    case Apto = 'apto';
    case NoApto = 'no_apto';

    public function label(): string
    {
        return match ($this) {
            self::Apto => 'Apto',
            self::NoApto => 'No apto',
        };
    }
}
