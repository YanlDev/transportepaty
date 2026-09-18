<?php

namespace App\Services\Costos;

use App\Models\ParametroFlota;

/**
 * Una línea sin calculadora de apoyo: su tasa es la que se escribe en el
 * tarifario y no hay cuenta detrás que mostrar. Es lo que corresponde a los
 * números que salen fuera del sistema (una negociación, el promedio de peajes
 * y viáticos de las rutas) y el punto de partida de cualquier línea nueva.
 */
class MetodoManual implements Metodo
{
    use LeeEntradas;

    public function derivar(array $entradas, ParametroFlota $flota): Derivacion
    {
        return new Derivacion($this->numero($entradas, 'tasa'), []);
    }

    public function entradasPorDefecto(): array
    {
        return [];
    }

    public function reglas(): array
    {
        return [];
    }
}
