<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Por dónde pasa una orden:
 *
 * - operaciones crea la pre-orden y elige al mecánico (`solicitada`);
 * - el mecánico arma el requerimiento —tareas, repuestos y fotos del antes—
 *   y lo envía (`requerimiento`);
 * - operaciones lo aprueba: recién ahí es una orden de trabajo (`pendiente`);
 * - el mecánico la empieza (`en_proceso`) y la termina (`por_revisar`);
 * - operaciones la valida (`finalizada`) o la devuelve (`observada`).
 *
 * Solo una orden finalizada cuenta como servicio hecho. Desde la pre-orden
 * la unidad está en taller: no recibe el apto de salida.
 */
enum EstadoOrdenTrabajo: string
{
    use HasLabel;

    case Solicitada = 'solicitada';
    case Requerimiento = 'requerimiento';
    case Pendiente = 'pendiente';
    case EnProceso = 'en_proceso';
    case PorRevisar = 'por_revisar';
    case Observada = 'observada';
    case Finalizada = 'finalizada';

    public function label(): string
    {
        return match ($this) {
            self::Solicitada => 'Pre-orden',
            self::Requerimiento => 'Requerimiento enviado',
            self::Pendiente => 'Pendiente',
            self::EnProceso => 'En proceso',
            self::PorRevisar => 'Por revisar',
            self::Observada => 'Observada',
            self::Finalizada => 'Finalizada',
        };
    }

    /**
     * Los estados que se eligen a mano en el formulario de una orden que ya
     * existe. Los demás salen de una acción: enviar, aprobar, terminar u
     * observar.
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
        return [self::Solicitada, self::Requerimiento, self::Pendiente, self::EnProceso, self::PorRevisar, self::Observada];
    }

    /**
     * Todavía no es una orden aprobada: el mecánico arma el requerimiento o
     * espera que operaciones lo apruebe. No recibe repuestos del almacén.
     */
    public function sinAprobar(): bool
    {
        return in_array($this, [self::Solicitada, self::Requerimiento], true);
    }

    /**
     * El mecánico puede trabajarla: marcar tareas, sumar fotos, terminarla.
     */
    public function enEjecucion(): bool
    {
        return in_array($this, [self::EnProceso, self::Observada], true);
    }
}
