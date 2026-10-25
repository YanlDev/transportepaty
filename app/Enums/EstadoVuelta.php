<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum EstadoVuelta: string
{
    use HasLabel;

    case Programada = 'programada';
    case EnCurso = 'en_curso';
    case Cerrada = 'cerrada';
    case Anulada = 'anulada';

    public function label(): string
    {
        return match ($this) {
            self::Programada => 'Programada',
            self::EnCurso => 'En curso',
            self::Cerrada => 'Cerrada',
            self::Anulada => 'Anulada',
        };
    }
}
