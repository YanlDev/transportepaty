<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum TipoCombustible: string
{
    use HasLabel;

    case Gasolina = 'gasolina';
    case Diesel = 'diesel';
    case Glp = 'glp';
    case Gnv = 'gnv';
    case Electrico = 'electrico';
    case Hibrido = 'hibrido';

    public function label(): string
    {
        return match ($this) {
            self::Gasolina => 'Gasolina',
            self::Diesel => 'Diésel',
            self::Glp => 'GLP',
            self::Gnv => 'GNV',
            self::Electrico => 'Eléctrico',
            self::Hibrido => 'Híbrido',
        };
    }
}
