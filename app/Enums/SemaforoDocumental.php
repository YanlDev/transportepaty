<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Estado de la documentación de una unidad, resumido en tres colores para poder
 * barrer la flota de un vistazo.
 */
enum SemaforoDocumental: string
{
    use HasLabel;

    /** Todo presente y vigente. */
    case Verde = 'verde';

    /** Nada falta ni está vencido, pero algo vence dentro del plazo de aviso. */
    case Ambar = 'ambar';

    /** Falta un documento obligatorio o alguno ya venció. */
    case Rojo = 'rojo';

    public function label(): string
    {
        return match ($this) {
            self::Verde => 'Al día',
            self::Ambar => 'Por vencer',
            self::Rojo => 'Con problemas',
        };
    }

    /**
     * El peor de dos semáforos. Sirve para resumir en una sola luz el estado de
     * una unidad completa, donde el tracto y la carreta tienen papeles aparte.
     */
    public function peorQue(self $otro): self
    {
        $orden = [self::Verde->value => 0, self::Ambar->value => 1, self::Rojo->value => 2];

        return $orden[$this->value] >= $orden[$otro->value] ? $this : $otro;
    }
}
