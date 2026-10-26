<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Los dos tramos de una vuelta. La ida sale de Juliaca o de la mina y baja
 * a la costa (concentrado, metálico, particular); el regreso sube de vuelta
 * a la sierra (escoria, materiales, particular, o vacía).
 */
enum SentidoTramo: string
{
    use HasLabel;

    case Ida = 'ida';
    case Regreso = 'regreso';

    public function label(): string
    {
        return match ($this) {
            self::Ida => 'Ida',
            self::Regreso => 'Regreso',
        };
    }

    /**
     * Un trayecto pertenece a la ida si termina en la costa (bajó) y al
     * regreso si termina en la sierra (subió).
     */
    public static function hacia(ZonaRuta $destino): self
    {
        return $destino->esSierra() ? self::Regreso : self::Ida;
    }
}
