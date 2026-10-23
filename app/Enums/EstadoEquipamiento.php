<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Cómo está el equipamiento de una carreta según su último conteo. No es una
 * columna: se deriva (ver `EstadoEquipamientoCarretas`).
 *
 * `Contado` es la carreta contada cuando ningún ítem tiene mínimo: se sabe
 * qué lleva, pero no hay contra qué compararlo.
 */
enum EstadoEquipamiento: string
{
    use HasLabel;

    case Falta = 'falta';
    case Completo = 'completo';
    case Contado = 'contado';
    case SinContar = 'sin_contar';

    public function label(): string
    {
        return match ($this) {
            self::Falta => 'Le falta',
            self::Completo => 'Completo',
            self::Contado => 'Contado',
            self::SinContar => 'Sin contar',
        };
    }

    public function prioridad(): int
    {
        return match ($this) {
            self::Falta => 0,
            self::SinContar => 1,
            self::Contado => 2,
            self::Completo => 3,
        };
    }
}
