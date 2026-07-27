<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum TipoVehiculo: string
{
    use HasLabel;

    case Tracto = 'tracto';
    case Carreta = 'carreta';

    public function label(): string
    {
        return match ($this) {
            self::Tracto => 'Tracto',
            self::Carreta => 'Carreta',
        };
    }

    /**
     * Indica si el tipo lleva caja de cambios. Solo el tracto es unidad
     * motriz; la carreta es remolcada y no tiene motor ni transmisión.
     */
    public function tieneCaja(): bool
    {
        return $this === self::Tracto;
    }

    /**
     * Tipos de documento exigibles para este tipo de vehículo.
     *
     * @return array<int, TipoDocumento>
     */
    public function documentosExigibles(): array
    {
        return array_values(array_filter(
            TipoDocumento::cases(),
            fn (TipoDocumento $documento): bool => $documento->aplicaA($this),
        ));
    }
}
