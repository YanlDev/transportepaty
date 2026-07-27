<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum TipoDocumento: string
{
    use HasLabel;

    case TarjetaPropiedad = 'tarjeta_propiedad';
    case Soat = 'soat';
    case RevisionTecnicaCarga = 'revision_tecnica_carga';
    case HabilitacionMtc = 'habilitacion_mtc';
    case Matpel = 'matpel';
    case Otro = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::TarjetaPropiedad => 'Tarjeta de propiedad',
            self::Soat => 'SOAT',
            self::RevisionTecnicaCarga => 'Revisión técnica de mercancías',
            self::HabilitacionMtc => 'TUC (habilitación MTC)',
            self::Matpel => 'MATPEL (materiales peligrosos)',
            self::Otro => 'Otro',
        };
    }

    /**
     * Indica si el documento aplica al tipo de vehículo indicado. Las carretas
     * exigen los mismos documentos que el tracto salvo el SOAT, que solo
     * corresponde a la unidad motriz.
     */
    public function aplicaA(TipoVehiculo $tipo): bool
    {
        if ($this === self::Soat) {
            return $tipo === TipoVehiculo::Tracto;
        }

        return true;
    }

    /**
     * Indica si este tipo de documento suele tener fecha de vencimiento.
     * Solo es una sugerencia para la UI; el vencimiento siempre es opcional.
     */
    public function sueleVencer(): bool
    {
        return match ($this) {
            self::Soat, self::RevisionTecnicaCarga, self::HabilitacionMtc, self::Matpel => true,
            self::TarjetaPropiedad, self::Otro => false,
        };
    }
}
