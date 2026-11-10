<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Cómo está un vale de compra hoy, que es lo que le importa a quien lo
 * recibe: si todavía sirve para comprar o no, y por qué.
 */
enum SituacionValeCompra: string
{
    use HasLabel;

    case PorAprobar = 'por_aprobar';
    case Vigente = 'vigente';
    case Parcial = 'parcial';
    case Usado = 'usado';
    case Vencido = 'vencido';
    case Rechazado = 'rechazado';
    case Anulado = 'anulado';

    public function label(): string
    {
        return match ($this) {
            self::PorAprobar => 'Por aprobar',
            self::Vigente => 'Vigente',
            self::Parcial => 'Usado en parte',
            self::Usado => 'Usado',
            self::Vencido => 'Vencido',
            self::Rechazado => 'Rechazado',
            self::Anulado => 'Anulado',
        };
    }

    /**
     * Si con el vale en esta situación todavía se puede comprar.
     */
    public function sirveParaComprar(): bool
    {
        return in_array($this, [self::Vigente, self::Parcial], true);
    }
}
