<?php

namespace App\Models\Concerns;

use App\Enums\EstadoDocumento;

/**
 * Comparte la lectura del vencimiento entre los documentos de vehículo y de
 * conductor, de modo que el plazo de aviso viva en un solo lugar y ambos
 * módulos coincidan siempre en qué es «vencido» y qué es «por vencer».
 *
 * El modelo que lo use debe tener la propiedad `fecha_vencimiento` casteada a
 * fecha.
 */
trait TieneVencimiento
{
    /**
     * Días de anticipación con los que un vencimiento próximo pasa a ámbar.
     */
    public const DIAS_AVISO_VENCIMIENTO = 30;

    /**
     * Situación del documento según su vencimiento. Nunca devuelve «faltante»:
     * el documento existe, y esa ausencia solo la conoce el dueño del expediente
     * al repasar su lista de obligatorios.
     *
     * Un documento que vence hoy sigue vigente hasta la medianoche, así que hoy
     * cuenta como «por vencer», no como «vencido».
     */
    public function estado(): EstadoDocumento
    {
        if ($this->fecha_vencimiento === null) {
            return EstadoDocumento::Vigente;
        }

        $hoy = now()->startOfDay();

        return match (true) {
            $this->fecha_vencimiento->lt($hoy) => EstadoDocumento::Vencido,
            $this->fecha_vencimiento->lte($hoy->addDays(self::DIAS_AVISO_VENCIMIENTO)) => EstadoDocumento::PorVencer,
            default => EstadoDocumento::Vigente,
        };
    }
}
