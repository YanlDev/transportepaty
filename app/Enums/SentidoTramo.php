<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Los dos tramos de una vuelta. La ida sube de la costa a la sierra (con
 * carga particular, materiales o escoria) y el regreso baja con el mineral
 * o con otra carga particular.
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
     * Un trayecto pertenece a la ida si termina en la sierra y al regreso si
     * termina en la costa.
     */
    public static function hacia(ZonaRuta $destino): self
    {
        return $destino->esSierra() ? self::Ida : self::Regreso;
    }
}
