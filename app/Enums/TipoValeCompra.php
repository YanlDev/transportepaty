<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Una compra normal la aprueba gerencia y necesita sustento del precio. Una
 * de emergencia es para una unidad parada en ruta: no espera cotizaciones y
 * también la aprueba el jefe de operaciones.
 */
enum TipoValeCompra: string
{
    use HasLabel;

    case Normal = 'normal';
    case Emergencia = 'emergencia';

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Normal',
            self::Emergencia => 'Emergencia en ruta',
        };
    }
}
