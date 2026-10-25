<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Para quién trabaja la unidad en la vuelta: basta una carga de Minsur
 * (concentrado, metálico, escoria, materiales…) para que sea de mina; si
 * todo lo que llevó fue particular, es particular.
 */
enum TipoVuelta: string
{
    use HasLabel;

    case Mina = 'mina';
    case Particular = 'particular';

    public function label(): string
    {
        return match ($this) {
            self::Mina => 'Mina',
            self::Particular => 'Particular',
        };
    }
}
