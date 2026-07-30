<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Lo que saca a una unidad de la programación del día. Son hechos de campo que
 * no se deducen de dónde está ni qué lleva: hay que registrarlos a mano, y son
 * lo que evita que una unidad no habida vuelva a figurar programada al día
 * siguiente porque nadie se acordó.
 */
enum TipoNovedad: string
{
    use HasLabel;

    case NoHabido = 'no_habido';
    case InfraccionMina = 'infraccion_mina';
    case EnMina = 'en_mina';
    case AdicionalFueraPrograma = 'adicional_fuera_programa';
    case Taller = 'taller';

    public function label(): string
    {
        return match ($this) {
            self::NoHabido => 'No habido',
            self::InfraccionMina => 'Infracción vigente en mina',
            self::EnMina => 'Ya se encuentra en mina',
            self::AdicionalFueraPrograma => 'Subió como adicional fuera de programa',
            self::Taller => 'En taller',
        };
    }

    /**
     * Motivo tal como debe leerse en la columna de no programables.
     */
    public function motivo(): string
    {
        return match ($this) {
            self::NoHabido => 'No habido',
            self::InfraccionMina => 'Infracción vigente en mina',
            self::EnMina => 'Ya está en mina; la cargan allá',
            self::AdicionalFueraPrograma => 'Subió como adicional; ya está en ciclo',
            self::Taller => 'En taller',
        };
    }

    /**
     * Todas impiden programar, pero se distinguen porque el motivo importa: no
     * es lo mismo una unidad que nadie encuentra que una que ya está arriba.
     */
    public function impideProgramar(): bool
    {
        return true;
    }
}
