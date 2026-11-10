<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Lo que cambia el equipamiento de una unidad sin pasar por el almacén: lo
 * que ya tenía en su primer conteo, lo que se le pasa a otra unidad y lo que
 * se da de baja. Lo que sale del almacén se lee de los vales de salida.
 */
enum TipoMovimientoEquipamiento: string
{
    use HasLabel;

    case SaldoInicial = 'saldo_inicial';
    case Traslado = 'traslado';
    case Baja = 'baja';

    public function label(): string
    {
        return match ($this) {
            self::SaldoInicial => 'Saldo inicial',
            self::Traslado => 'Traslado a otra unidad',
            self::Baja => 'Baja',
        };
    }
}
