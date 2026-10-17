<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/** Cómo llega la llanta a la posición donde se instala. */
enum CondicionLlanta: string
{
    use HasLabel;

    case Nueva = 'nueva';
    case Reencauchada = 'reencauchada';
    case Usada = 'usada';

    public function label(): string
    {
        return match ($this) {
            self::Nueva => 'Nueva',
            self::Reencauchada => 'Reencauchada',
            self::Usada => 'Usada',
        };
    }
}
