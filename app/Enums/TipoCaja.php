<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum TipoCaja: string
{
    use HasLabel;

    case Mecanica = 'mecanica';
    case Automatica = 'automatica';

    public function label(): string
    {
        return match ($this) {
            self::Mecanica => 'Mecánica',
            self::Automatica => 'Automática',
        };
    }
}
