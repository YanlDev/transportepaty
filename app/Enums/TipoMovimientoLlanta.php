<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Lo que le pasa a una llanta mientras está en una unidad. La inspección es
 * la que trae la profundidad del dibujo, y de la última medida sale su
 * semáforo (ver `EstadoLlanta`).
 */
enum TipoMovimientoLlanta: string
{
    use HasLabel;

    case Instalacion = 'instalacion';
    case Inspeccion = 'inspeccion';
    case Rotacion = 'rotacion';
    case Reparacion = 'reparacion';
    case Retiro = 'retiro';

    public function label(): string
    {
        return match ($this) {
            self::Instalacion => 'Instalación',
            self::Inspeccion => 'Medición',
            self::Rotacion => 'Rotación',
            self::Reparacion => 'Reparación',
            self::Retiro => 'Retiro',
        };
    }
}
