<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum TipoVehiculo: string
{
    use HasLabel;

    case Auto = 'auto';
    case Camioneta = 'camioneta';
    case Suv = 'suv';
    case Camion = 'camion';
    case Bus = 'bus';
    case Minivan = 'minivan';
    case Moto = 'moto';
    case Maquinaria = 'maquinaria';

    public function label(): string
    {
        return match ($this) {
            self::Auto => 'Auto',
            self::Camioneta => 'Camioneta',
            self::Suv => 'SUV',
            self::Camion => 'Camión',
            self::Bus => 'Bus',
            self::Minivan => 'Minivan',
            self::Moto => 'Motocicleta',
            self::Maquinaria => 'Maquinaria',
        };
    }
}
