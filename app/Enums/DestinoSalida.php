<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * A dónde va lo que sale del almacén. Nada sale sin destino: es lo que
 * permite saber cuánto cuesta cada unidad y cada trabajo.
 */
enum DestinoSalida: string
{
    use HasLabel;

    case OrdenTrabajo = 'orden_trabajo';
    case Unidad = 'unidad';
    case Consumo = 'consumo';

    public function label(): string
    {
        return match ($this) {
            self::OrdenTrabajo => 'Orden de trabajo',
            self::Unidad => 'Unidad',
            self::Consumo => 'Consumo del taller',
        };
    }
}
