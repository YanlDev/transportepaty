<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum PosicionFoto: string
{
    use HasLabel;

    case Frente = 'frente';
    case Trasera = 'trasera';
    case LateralIzquierdo = 'lateral_izquierdo';
    case LateralDerecho = 'lateral_derecho';
    case Interior = 'interior';
    case Motor = 'motor';
    case Tablero = 'tablero';
    case Otro = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::Frente => 'Frente',
            self::Trasera => 'Trasera',
            self::LateralIzquierdo => 'Lateral izquierdo',
            self::LateralDerecho => 'Lateral derecho',
            self::Interior => 'Interior',
            self::Motor => 'Motor',
            self::Tablero => 'Tablero',
            self::Otro => 'Otro'
        };
    }
}
