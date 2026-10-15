<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * El semáforo de una unidad frente a su plan. No es una columna: se deriva
 * del último kilometraje y de los servicios hechos (ver `ProximoMantenimiento`).
 *
 * `SinDatos` es la unidad de la que todavía no se sabe lo suficiente: sin un
 * kilometraje registrado o sin ningún servicio del plan, no hay cuenta que
 * hacer, y decir «al día» sería inventarlo.
 */
enum EstadoMantenimiento: string
{
    use HasLabel;

    case Vencido = 'vencido';
    case Proximo = 'proximo';
    case AlDia = 'al_dia';
    case SinDatos = 'sin_datos';

    public function label(): string
    {
        return match ($this) {
            self::Vencido => 'Vencido',
            self::Proximo => 'Próximo',
            self::AlDia => 'Al día',
            self::SinDatos => 'Sin datos',
        };
    }

    /** Para ordenar la flota: lo vencido primero, lo que no se sabe al final. */
    public function prioridad(): int
    {
        return match ($this) {
            self::Vencido => 0,
            self::Proximo => 1,
            self::AlDia => 2,
            self::SinDatos => 3,
        };
    }
}
