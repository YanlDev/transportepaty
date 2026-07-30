<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Si la unidad lleva carga encima o viaja vacía. Es lo que decide si puede
 * entrar a la programación de subida a mina: solo se programa lo descargado.
 */
enum EstadoCarga: string
{
    use HasLabel;

    case Cargado = 'cargado';
    case Vacio = 'vacio';

    public function label(): string
    {
        return match ($this) {
            self::Cargado => 'Cargado',
            self::Vacio => 'Vacío',
        };
    }

    /**
     * Indica si la unidad está libre para tomar una nueva carga. Es una de las
     * condiciones de elegibilidad para la programación de subida a mina.
     */
    public function estaDescargada(): bool
    {
        return $this === self::Vacio;
    }
}
