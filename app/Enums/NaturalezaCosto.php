<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Si el costo se le puede atribuir al viaje o es estructura que el viaje ayuda
 * a pagar.
 *
 * La distinción decide hasta dónde se puede bajar una tarifa en una
 * negociación: un precio que no cubre los costos directos hace perder plata en
 * cada vuelta, mientras que uno que los cubre pero aporta poco a los
 * indirectos puede tener sentido para llenar un retorno que si no viaja vacío.
 */
enum NaturalezaCosto: string
{
    use HasLabel;

    case Directo = 'directo';
    case Indirecto = 'indirecto';

    public function label(): string
    {
        return match ($this) {
            self::Directo => 'Directo',
            self::Indirecto => 'Indirecto',
        };
    }
}
