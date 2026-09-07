<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Contra qué se multiplica un componente de costo para llegar a su importe en
 * un viaje. No es una etiqueta contable: la unidad cuesta por día aunque esté
 * parada esperando turno de carga, y cuesta por kilómetro solo cuando rueda.
 * Cotizar todo por distancia castiga las rutas cortas con mucha espera.
 */
enum TipoComponente: string
{
    use HasLabel;

    case FijoDia = 'fijo_dia';
    case VariableKm = 'variable_km';

    public function label(): string
    {
        return match ($this) {
            self::FijoDia => 'Fijo (por día)',
            self::VariableKm => 'Variable (por km)',
        };
    }

    /**
     * La unidad en la que se lee la tasa del componente.
     */
    public function unidad(): string
    {
        return match ($this) {
            self::FijoDia => 'S/ por día',
            self::VariableKm => 'S/ por km',
        };
    }
}
