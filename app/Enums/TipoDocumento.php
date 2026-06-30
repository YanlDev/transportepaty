<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum TipoDocumento: string
{
    use HasLabel;

    case TarjetaPropiedad = 'tarjeta_propiedad';
    case Soat = 'soat';
    case RevisionTecnica = 'revision_tecnica';
    case Seguro = 'seguro';
    case PermisoLunasPolarizadas = 'permiso_lunas_polarizadas';
    case Otro = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::TarjetaPropiedad => 'Tarjeta de propiedad',
            self::Soat => 'SOAT',
            self::RevisionTecnica => 'Revisión técnica',
            self::Seguro => 'Seguro vehicular',
            self::PermisoLunasPolarizadas => 'Permiso de lunas polarizadas',
            self::Otro => 'Otro',
        };
    }

    /**
     * Indica si este tipo de documento suele tener fecha de vencimiento.
     * Solo es una sugerencia para la UI; el vencimiento siempre es opcional.
     */
    public function sueleVencer(): bool
    {
        return match ($this) {
            self::Soat, self::RevisionTecnica, self::Seguro => true,
            self::TarjetaPropiedad, self::PermisoLunasPolarizadas, self::Otro => false,
        };
    }
}
