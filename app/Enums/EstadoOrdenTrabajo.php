<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Por dónde pasa una orden: operaciones la pide (solicitada), taller la
 * acepta y la asigna (pendiente), el mecánico la empieza (en proceso) y la
 * termina (por revisar), y taller la aprueba (finalizada) o la devuelve con
 * su observación (observada).
 *
 * Solo una orden finalizada cuenta como servicio hecho. Una solicitada
 * todavía no es trabajo de taller: no bloquea el apto de salida.
 */
enum EstadoOrdenTrabajo: string
{
    use HasLabel;

    case Solicitada = 'solicitada';
    case Pendiente = 'pendiente';
    case EnProceso = 'en_proceso';
    case PorRevisar = 'por_revisar';
    case Observada = 'observada';
    case Finalizada = 'finalizada';

    public function label(): string
    {
        return match ($this) {
            self::Solicitada => 'Solicitada',
            self::Pendiente => 'Pendiente',
            self::EnProceso => 'En proceso',
            self::PorRevisar => 'Por revisar',
            self::Observada => 'Observada',
            self::Finalizada => 'Finalizada',
        };
    }

    /**
     * Los estados que taller elige a mano en el formulario de la orden. Los
     * demás salen de una acción: pedir, terminar u observar.
     *
     * @return list<self>
     */
    public static function deTaller(): array
    {
        return [self::Pendiente, self::EnProceso, self::Finalizada];
    }

    /**
     * Con la orden así, la unidad está en taller y no recibe el apto.
     *
     * @return list<self>
     */
    public static function enTaller(): array
    {
        return [self::Pendiente, self::EnProceso, self::PorRevisar, self::Observada];
    }

    /**
     * El mecánico puede trabajarla: marcar tareas, sumar fotos, terminarla.
     */
    public function enEjecucion(): bool
    {
        return in_array($this, [self::EnProceso, self::Observada], true);
    }
}
