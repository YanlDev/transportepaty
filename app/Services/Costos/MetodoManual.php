<?php

namespace App\Services\Costos;

use App\Models\ParametroFlota;

/**
 * La tasa se escribe a mano. Es la salida para los componentes cuya cuenta
 * vive fuera del sistema (una cotización de un proveedor, un número que salió
 * de una negociación) y el punto de partida de cualquier componente nuevo:
 * primero se carga el número que ya se usa, y recién después se decide si vale
 * la pena derivarlo.
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
        return ['tasa' => 0];
    }

    public function reglas(): array
    {
        return ['tasa' => ['required', 'numeric', 'min:0']];
    }
}
