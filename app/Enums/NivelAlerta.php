<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Gravedad de una inconsistencia detectada. Son dos niveles y ninguno bloquea:
 * la fila se guarda siempre, porque a veces el dato está bien y la realidad fue
 * rara, y el sistema no está para decidir eso por ti.
 */
enum NivelAlerta: string
{
    use HasLabel;

    case Imposible = 'imposible';
    case Improbable = 'improbable';

    public function label(): string
    {
        return match ($this) {
            self::Imposible => 'Imposible',
            self::Improbable => 'Improbable',
        };
    }

    public function descripcion(): string
    {
        return match ($this) {
            self::Imposible => 'Contradice la secuencia del circuito o la física: alguien escribió mal el dato.',
            self::Improbable => 'Puede ser, pero es raro para el tiempo transcurrido. Conviene confirmarlo.',
        };
    }

    /**
     * Peso para ordenar: lo imposible se mira antes que lo improbable.
     */
    public function prioridad(): int
    {
        return match ($this) {
            self::Imposible => 0,
            self::Improbable => 1,
        };
    }
}
