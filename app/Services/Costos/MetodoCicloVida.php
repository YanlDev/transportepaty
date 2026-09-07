<?php

namespace App\Services\Costos;

use App\Models\ParametroFlota;

/**
 * Costo por kilómetro de algo que se consume en vidas sucesivas sobre el mismo
 * juego de piezas: los neumáticos, que se compran nuevos, se reencauchan una
 * vez, se reencauchan otra y recién ahí se descartan.
 *
 * Es plata total del ciclo dividida entre kilómetros totales del ciclo. Ojo
 * con la diferencia contra `MetodoFrecuenciaKm`: acá las etapas son una
 * después de la otra sobre las mismas ruedas, así que sumar el costo por km de
 * cada vida daría tres veces el costo real.
 */
class MetodoCicloVida implements Metodo
{
    use LeeEntradas;

    public function derivar(array $entradas, ParametroFlota $flota): Derivacion
    {
        $costoTotal = 0.0;
        $kmTotales = 0.0;

        foreach ($this->filas($entradas, 'vidas') as $vida) {
            $costoTotal += $this->numero($vida, 'costo');
            $kmTotales += $this->numero($vida, 'km');
        }

        return new Derivacion($this->dividir($costoTotal, $kmTotales), [
            $this->paso('Costo de todo el ciclo', $costoTotal),
            $this->paso('Kilómetros de todo el ciclo', $kmTotales, 'km'),
        ]);
    }

    public function entradasPorDefecto(): array
    {
        return ['vidas' => [['concepto' => '', 'costo' => 0, 'km' => 0]]];
    }

    public function reglas(): array
    {
        return [
            'vidas' => ['required', 'array', 'min:1'],
            'vidas.*.concepto' => ['nullable', 'string', 'max:100'],
            'vidas.*.costo' => ['required', 'numeric', 'min:0'],
            'vidas.*.km' => ['required', 'numeric', 'min:1'],
        ];
    }
}
