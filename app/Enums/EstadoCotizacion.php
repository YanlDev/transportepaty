<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * En qué punto está una cotización frente al cliente. No es un estado interno
 * de edición: marca lo que pasó afuera —si ya se mandó, si la aceptaron—, que
 * es lo que decide si todavía se puede negociar el precio o ya está cerrado.
 */
enum EstadoCotizacion: string
{
    use HasLabel;

    case Borrador = 'borrador';
    case Enviada = 'enviada';
    case Aceptada = 'aceptada';
    case Rechazada = 'rechazada';

    public function label(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::Enviada => 'Enviada',
            self::Aceptada => 'Aceptada',
            self::Rechazada => 'Rechazada',
        };
    }
}
