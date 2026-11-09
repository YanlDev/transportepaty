<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Un conteo físico se va llenando abierto y no toca el kardex hasta que se
 * cierra: recién ahí sus diferencias pasan a ser movimientos.
 */
enum EstadoInventario: string
{
    use HasLabel;

    case Abierto = 'abierto';
    case Cerrado = 'cerrado';

    public function label(): string
    {
        return match ($this) {
            self::Abierto => 'Abierto',
            self::Cerrado => 'Cerrado',
        };
    }
}
