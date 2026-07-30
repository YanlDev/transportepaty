<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Para quién trabaja la unidad en el viaje. El circuito de mina es de Minsur
 * —coordinado a través de Cargo Transport— y la carga de terceros que se toma
 * en Lima para no volver vacío a Juliaca es particular.
 */
enum Cliente: string
{
    use HasLabel;

    case Minsur = 'minsur';
    case Particular = 'particular';

    public function label(): string
    {
        return match ($this) {
            self::Minsur => 'Minsur',
            self::Particular => 'Particular',
        };
    }
}
