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

    /**
     * El tramo de un trayecto dentro de la vuelta. La vuelta empieza cuando
     * la unidad sale de la sierra (Juliaca, la mina, Arequipa, Cusco) —ahí
     * carga combustible y recibe viáticos— y termina cuando llega de nuevo a
     * la sierra. Así:
     *
     * - lo que sale de la sierra es ida (incluido Juliaca ⇢ mina vacío);
     * - lo que llega a la sierra desde la costa es regreso;
     * - lo que va de costa a costa (Pisco → Callao) sigue en el tramo en que
     *   iba la vuelta.
     */
    public static function entre(?ZonaRuta $origen, ?ZonaRuta $destino, bool $yaEmpezoElRegreso): self
    {
        if ($origen?->esSierra()) {
            return self::Ida;
        }

        if ($destino?->esSierra()) {
            return self::Regreso;
        }

        return $yaEmpezoElRegreso ? self::Regreso : self::Ida;
    }
}
