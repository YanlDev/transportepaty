<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Situación de un documento obligatorio concreto. A diferencia de
 * SemaforoDocumental, que resume la unidad entera en tres colores, esto describe
 * un solo papel para poder listarlos uno por uno.
 */
enum EstadoDocumento: string
{
    use HasLabel;

    /** Cargado y sin vencer, o sin fecha de vencimiento. */
    case Vigente = 'vigente';

    /** Cargado, pero vence dentro del plazo de aviso. */
    case PorVencer = 'por_vencer';

    /** Cargado y con la fecha de vencimiento ya pasada. */
    case Vencido = 'vencido';

    /** Nunca se cargó. */
    case Faltante = 'faltante';

    public function label(): string
    {
        return match ($this) {
            self::Vigente => 'Vigente',
            self::PorVencer => 'Por vencer',
            self::Vencido => 'Vencido',
            self::Faltante => 'Sin cargar',
        };
    }
}
