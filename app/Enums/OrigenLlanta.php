<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * De dónde sale la llanta que se monta: del almacén con su vale, de otra
 * unidad de la que se retiró, o ya estaba puesta antes de que existiera el
 * almacén (la carga inicial).
 */
enum OrigenLlanta: string
{
    use HasLabel;

    case Almacen = 'almacen';
    case Retirada = 'retirada';
    case Previa = 'previa';

    public function label(): string
    {
        return match ($this) {
            self::Almacen => 'Del almacén',
            self::Retirada => 'Retirada de otra unidad',
            self::Previa => 'Ya estaba montada (carga inicial)',
        };
    }
}
