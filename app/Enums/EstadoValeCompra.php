<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Por dónde va la decisión sobre un vale de compra. Que esté usado, vencido
 * o anulado no es un estado: sale de sus ingresos, de su fecha y de su
 * anulación (ver `ValeCompra::situacion()`).
 */
enum EstadoValeCompra: string
{
    use HasLabel;

    case Solicitado = 'solicitado';
    case Aprobado = 'aprobado';
    case Rechazado = 'rechazado';

    public function label(): string
    {
        return match ($this) {
            self::Solicitado => 'Por aprobar',
            self::Aprobado => 'Aprobado',
            self::Rechazado => 'Rechazado',
        };
    }
}
