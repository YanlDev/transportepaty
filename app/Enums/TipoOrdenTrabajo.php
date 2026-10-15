<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Preventiva es un servicio del plan de la marca (la X, S, M o L de Scania):
 * al cerrarse, reinicia su contador. Correctiva es todo lo demás —una falla,
 * un cambio suelto— y no toca el plan.
 */
enum TipoOrdenTrabajo: string
{
    use HasLabel;

    case Preventiva = 'preventiva';
    case Correctiva = 'correctiva';

    public function label(): string
    {
        return match ($this) {
            self::Preventiva => 'Preventiva',
            self::Correctiva => 'Correctiva',
        };
    }
}
