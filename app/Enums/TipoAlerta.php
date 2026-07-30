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

    case RutaIncompatibleConCarga = 'ruta_incompatible_con_carga';
    case UbicacionFueraDeRuta = 'ubicacion_fuera_de_ruta';
    case RutaIncompleta = 'ruta_incompleta';
    case UbicacionSinResolver = 'ubicacion_sin_resolver';
    case SinConductor = 'sin_conductor';
    case ConductorDistintoAlAsignado = 'conductor_distinto_al_asignado';
    case CarretaDistintaALaAsignada = 'carreta_distinta_a_la_asignada';
    case SaltoDeFase = 'salto_de_fase';
    case CargaCambioFueraDePunto = 'carga_cambio_fuera_de_punto';
    case AvanceImposible = 'avance_imposible';
    case UnidadDetenida = 'unidad_detenida';

    public function label(): string
    {
        return match ($this) {
            self::RutaIncompatibleConCarga => 'La ruta no corresponde a la carga',
            self::UbicacionFueraDeRuta => 'La unidad está fuera de la ruta que declara',
            self::RutaIncompleta => 'Falta el origen o el destino',
            self::UbicacionSinResolver => 'Ubicación no reconocida',
            self::SinConductor => 'Sin conductor asignado',
            self::ConductorDistintoAlAsignado => 'El conductor no es el de la asignación vigente',
            self::CarretaDistintaALaAsignada => 'La carreta no es la de la asignación vigente',
            self::SaltoDeFase => 'Se saltó una etapa del circuito',
            self::CargaCambioFueraDePunto => 'La carga cambió fuera de un punto de carga o descarga',
            self::AvanceImposible => 'Avanzó más de lo que se puede en el tiempo transcurrido',
            self::UnidadDetenida => 'La unidad lleva días sin moverse',
        };
    }

    public function nivel(): NivelAlerta
    {
        return match ($this) {
            self::RutaIncompatibleConCarga,
            self::UbicacionFueraDeRuta,
            self::SaltoDeFase,
            self::AvanceImposible => NivelAlerta::Imposible,

            self::RutaIncompleta,
            self::UbicacionSinResolver,
            self::SinConductor,
            self::ConductorDistintoAlAsignado,
            self::CarretaDistintaALaAsignada,
            self::CargaCambioFueraDePunto,
            self::UnidadDetenida => NivelAlerta::Improbable,
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
            self::SaltoDeFase,
            self::CargaCambioFueraDePunto,
            self::AvanceImposible,
            self::UnidadDetenida => true,
            default => false,
        };
    }
}
