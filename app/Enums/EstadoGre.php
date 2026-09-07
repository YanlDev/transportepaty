<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Dónde está la guía en su camino hacia SUNAT. El envío es asíncrono: SUNAT
 * responde con un ticket y recién en una segunda consulta entrega el CDR, así
 * que "enviada" y "aceptada" son estados distintos y ambos son normales.
 */
enum EstadoGre: string
{
    use HasLabel;

    case Pendiente = 'pendiente';
    case Generada = 'generada';
    case Enviada = 'enviada';
    case Aceptada = 'aceptada';
    case Rechazada = 'rechazada';
    case Anulada = 'anulada';

    public function label(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente de emitir',
            self::Generada => 'XML generado',
            self::Enviada => 'Enviada, esperando CDR',
            self::Aceptada => 'Aceptada por SUNAT',
            self::Rechazada => 'Rechazada por SUNAT',
            self::Anulada => 'Anulada',
        };
    }

    /**
     * Si el documento ya no admite reenvío por estar cerrado ante SUNAT.
     */
    public function esFinal(): bool
    {
        return in_array($this, [self::Aceptada, self::Anulada], true);
    }
}
