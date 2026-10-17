<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * El semáforo de una llanta según la profundidad del dibujo en su última
 * medición. No es una columna: se deriva de los movimientos. Los umbrales
 * están en `transpaty.mantenimiento.llantas_*_mm`.
 */
enum EstadoLlanta: string
{
    use HasLabel;

    case Cambiar = 'cambiar';
    case Revisar = 'revisar';
    case Bien = 'bien';
    case SinMedir = 'sin_medir';

    public function label(): string
    {
        return match ($this) {
            self::Cambiar => 'Cambiar',
            self::Revisar => 'Revisar',
            self::Bien => 'Bien',
            self::SinMedir => 'Sin medir',
        };
    }

    /** Para ordenar: lo que hay que cambiar primero, lo que no se sabe al final. */
    public function prioridad(): int
    {
        return match ($this) {
            self::Cambiar => 0,
            self::Revisar => 1,
            self::Bien => 2,
            self::SinMedir => 3,
        };
    }

    public static function deProfundidad(?float $milimetros): self
    {
        if ($milimetros === null) {
            return self::SinMedir;
        }

        if ($milimetros <= (float) config('transpaty.mantenimiento.llantas_cambiar_mm')) {
            return self::Cambiar;
        }

        if ($milimetros <= (float) config('transpaty.mantenimiento.llantas_revisar_mm')) {
            return self::Revisar;
        }

        return self::Bien;
    }
}
