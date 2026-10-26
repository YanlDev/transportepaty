<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Qué es cada fila de una tarjeta de la pizarra: galones en un punto, plata
 * para el conductor o peajes.
 */
enum TipoLineaAbastecimiento: string
{
    use HasLabel;

    case Combustible = 'combustible';
    case Viatico = 'viatico';
    case Peaje = 'peaje';

    public function label(): string
    {
        return match ($this) {
            self::Combustible => 'Combustible',
            self::Viatico => 'Viático',
            self::Peaje => 'Peaje',
        };
    }
}
