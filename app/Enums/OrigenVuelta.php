<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * De dónde salió la vuelta. Las del historial se arman solas desde las GR y
 * se pueden rehacer cuando se quiera; las programadas las cargó una persona
 * y la reconstrucción nunca las toca.
 */
enum OrigenVuelta: string
{
    use HasLabel;

    case Historial = 'historial';
    case Programacion = 'programacion';

    public function label(): string
    {
        return match ($this) {
            self::Historial => 'Armada desde las GR',
            self::Programacion => 'Programada',
        };
    }
}
