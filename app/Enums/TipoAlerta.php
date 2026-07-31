<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Catálogo de lo que el sistema sabe detectar. Cada caso trae su gravedad, de
 * modo que agregar una comprobación nueva sea agregar un caso acá y no repartir
 * criterios por los validadores.
 *
 * Las primeras se resuelven mirando una sola fila; las últimas necesitan
 * compararla contra el estado anterior de la misma unidad.
 */
enum TipoAlerta: string
{
    use HasLabel;

    case RutaIncompleta = 'ruta_incompleta';
    case SinConductor = 'sin_conductor';
    case ConductorDistintoAlAsignado = 'conductor_distinto_al_asignado';
    case CarretaDistintaALaAsignada = 'carreta_distinta_a_la_asignada';
    case SaltoDeFase = 'salto_de_fase';

    public function label(): string
    {
        return match ($this) {
            self::RutaIncompleta => 'Falta el origen o el destino',
            self::SinConductor => 'Sin conductor asignado',
            self::ConductorDistintoAlAsignado => 'El conductor no es el de la asignación vigente',
            self::CarretaDistintaALaAsignada => 'La carreta no es la de la asignación vigente',
            self::SaltoDeFase => 'Se saltó una etapa del circuito',
        };
    }

    public function nivel(): NivelAlerta
    {
        return match ($this) {
            self::SaltoDeFase => NivelAlerta::Imposible,

            self::RutaIncompleta,
            self::SinConductor,
            self::ConductorDistintoAlAsignado,
            self::CarretaDistintaALaAsignada => NivelAlerta::Improbable,
        };
    }

    /**
     * Indica si la comprobación necesita el estado anterior de la unidad. Las
     * que no lo necesitan pueden correrse sobre una fila suelta, apenas se
     * escribe.
     */
    public function requiereEstadoAnterior(): bool
    {
        return match ($this) {
            self::SaltoDeFase => true,
            default => false,
        };
    }
}
