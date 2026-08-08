<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum EstadoAsistencia: string
{
    use HasLabel;

    case Asistencia = 'asistencia';
    case Falta = 'falta';
    case Vacaciones = 'vacaciones';
    case Descanso = 'descanso';

    public function label(): string
    {
        return match ($this) {
            self::Asistencia => 'Asistencia',
            self::Falta => 'Falta',
            self::Vacaciones => 'Vacaciones',
            self::Descanso => 'Descanso',
        };
    }
}
