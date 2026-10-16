<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Los cuatro niveles del preventivo, iguales para todas las marcas: el taller
 * habla de «le toca la S» sea Scania o Volvo. Lo que cambia por marca son los
 * km, los meses y lo que lleva cada uno (ver `ServicioMantenimiento`).
 *
 * Van anidados: cada nivel incluye a los anteriores, así que una marca puede
 * usar solo los primeros (X y S, o X, S y M), nunca saltarse uno.
 */
enum NivelMantenimiento: string
{
    use HasLabel;

    case X = 'X';
    case S = 'S';
    case M = 'M';
    case L = 'L';

    public function label(): string
    {
        return 'Servicio '.$this->value;
    }

    /** El `nivel` del servicio: 1 para la X, 4 para la L. */
    public function nivel(): int
    {
        return match ($this) {
            self::X => 1,
            self::S => 2,
            self::M => 3,
            self::L => 4,
        };
    }
}
