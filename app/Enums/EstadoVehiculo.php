<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum EstadoVehiculo: string
{
    use HasLabel;

    case Activo = 'activo';
    case EnMantenimiento = 'en_mantenimiento';
    case Inactivo = 'inactivo';
    case DadoDeBaja = 'dado_de_baja';

    public function label(): string
    {
        return match ($this) {
            self::Activo => 'Operativo',
            self::EnMantenimiento => 'En mantenimiento',
            self::Inactivo => 'Inactivo',
            self::DadoDeBaja => 'Dado de baja',
        };
    }
}
