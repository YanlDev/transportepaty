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
            self::Regreso => 'Vuelta',
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
     * la unidad sale de la base (Juliaca o la mina) —ahí carga combustible y
     * recibe viáticos— y termina cuando vuelve a llegar a la base. El Sur y
     * Cusco son destinos de paso: de Tacna, Moquegua, Arequipa, Ilo o Cusco
     * la unidad sigue a Juliaca, y eso todavía es el regreso. Así:
     *
     * - lo que sale de la base es ida (incluido Juliaca ⇢ mina vacío);
     * - lo que sube de la costa a la sierra es regreso;
     * - lo que llega a la base desde el Sur o Cusco es regreso (el final);
     * - lo que baja del Sur o Cusco a la costa es ida;
     * - lo demás (Pisco → Callao, Tacna → Ilo) sigue en el tramo en que iba
     *   la vuelta.
     */
    public static function entre(?ZonaRuta $origen, ?ZonaRuta $destino, bool $yaEmpezoElRegreso): self
    {
        if ($origen?->esBase()) {
            return self::Ida;
        }

        if ($destino?->esSierra() && ($origen === null || ! $origen->esSierra() || $destino->esBase())) {
            return self::Regreso;
        }

        if ($origen?->esSierra() && $destino !== null && ! $destino->esSierra()) {
            return self::Ida;
        }

        return $yaEmpezoElRegreso ? self::Regreso : self::Ida;
    }
}
