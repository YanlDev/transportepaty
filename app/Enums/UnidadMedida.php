<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Cómo se cuenta un artículo en el almacén y cómo se compra. Lo que se compra
 * en una unidad y se despacha en otra (el aceite en cilindro, por litros)
 * lleva el factor en el artículo.
 */
enum UnidadMedida: string
{
    use HasLabel;

    case Unidad = 'und';
    case Litro = 'lt';
    case Galon = 'gl';
    case Kilogramo = 'kg';
    case Metro = 'm';
    case Juego = 'jgo';
    case Par = 'par';
    case Caja = 'cja';
    case Cilindro = 'cil';
    case Balde = 'bld';
    case Rollo = 'rll';
    case Paquete = 'pqt';

    public function label(): string
    {
        return match ($this) {
            self::Unidad => 'Unidad',
            self::Litro => 'Litro',
            self::Galon => 'Galón',
            self::Kilogramo => 'Kilogramo',
            self::Metro => 'Metro',
            self::Juego => 'Juego',
            self::Par => 'Par',
            self::Caja => 'Caja',
            self::Cilindro => 'Cilindro',
            self::Balde => 'Balde',
            self::Rollo => 'Rollo',
            self::Paquete => 'Paquete',
        };
    }

    /**
     * La abreviatura que va junto a las cantidades: «12 LT».
     */
    public function abreviatura(): string
    {
        return strtoupper($this->value);
    }
}
