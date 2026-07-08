<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum ResultadoActivacion: string
{
    use HasLabel;

    case SinNovedad = 'sin_novedad';
    case Anomalia = 'anomalia';

    public function label(): string
    {
        return match ($this) {
            self::SinNovedad => 'Sin novedad',
            self::Anomalia => 'Anomalía detectada',
        };
    }
}
