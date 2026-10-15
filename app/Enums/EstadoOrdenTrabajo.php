<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Las mismas tres etapas del cuadro de trabajos de taller. Solo una orden
 * finalizada cuenta como servicio hecho.
 */
enum EstadoOrdenTrabajo: string
{
    use HasLabel;

    case Pendiente = 'pendiente';
    case EnProceso = 'en_proceso';
    case Finalizada = 'finalizada';

    public function label(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::EnProceso => 'En proceso',
            self::Finalizada => 'Finalizada',
        };
    }
}
