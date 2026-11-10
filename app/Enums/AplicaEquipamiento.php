<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * A qué unidades les toca un ítem de equipamiento: las fajas van en la
 * carreta; el extintor y el botiquín, en el tracto.
 */
enum AplicaEquipamiento: string
{
    use HasLabel;

    case Carreta = 'carreta';
    case Tracto = 'tracto';
    case Todas = 'todas';

    public function label(): string
    {
        return match ($this) {
            self::Carreta => 'Carretas',
            self::Tracto => 'Tractos',
            self::Todas => 'Todas las unidades',
        };
    }

    public function incluye(TipoVehiculo $tipo): bool
    {
        return match ($this) {
            self::Todas => true,
            self::Carreta => $tipo === TipoVehiculo::Carreta,
            self::Tracto => $tipo === TipoVehiculo::Tracto,
        };
    }
}
