<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Lo que dice el último aviso de WhatsApp sobre un tracto: para qué carga
 * quedó programado, o que ya quedó libre. No es un catálogo cerrado de tipos
 * de carga (para eso está `TipoCarga`, que gobierna los viajes ya cerrados
 * con GR) — acá solo interesa lo que el cliente reporta día a día, así que se
 * suman casos según aparezcan avisos nuevos.
 */
enum EstadoProgramacion: string
{
    use HasLabel;

    case Metalico = 'metalico';
    case Concentrado = 'concentrado';
    case Escoria = 'escoria';
    case Ransa = 'ransa';
    case Polytex = 'polytex';
    case Particular = 'particular';
    case Salida = 'salida';
    case Libre = 'libre';

    public function label(): string
    {
        return match ($this) {
            self::Metalico => 'Metálico',
            self::Concentrado => 'Concentrado',
            self::Escoria => 'Escoria',
            self::Ransa => 'Ransa',
            self::Polytex => 'Polytex',
            self::Particular => 'Particular',
            self::Salida => 'Salida',
            self::Libre => 'Libre',
        };
    }
}
