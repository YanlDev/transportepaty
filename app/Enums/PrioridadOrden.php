<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Qué orden va primero en la lista del mecánico: la urgente es la de una
 * unidad con ticket por salir o parada en ruta.
 */
enum PrioridadOrden: string
{
    use HasLabel;

    case Normal = 'normal';
    case Urgente = 'urgente';

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Normal',
            self::Urgente => 'Urgente',
        };
    }
}
