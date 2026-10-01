<?php

namespace App\Services\Costos;

use App\Models\ParametroFlota;

/**
 * Un gasto que se paga por año —los sueldos de oficina, los seguros, el
 * alquiler de la base— repartido entre los días que una unidad puede vender
 * en el año.
 *
 * Se pide por unidad y no el total de la empresa: así no hace falta decir
 * cuántas unidades hay, y no se cuela la estructura de nadie más.
 */
class MetodoProrrateoAnual implements Metodo
{
    use LeeEntradas;

    public function derivar(array $entradas, ParametroFlota $flota): Derivacion
    {
        $montoAnual = $this->numero($entradas, 'monto_anual_unidad');

        return new Derivacion($this->dividir($montoAnual, $flota->diasDisponibles()), [
            $this->paso('Monto anual por unidad', $montoAnual),
            $this->paso('Días disponibles por unidad', $flota->diasDisponibles(), 'dias'),
        ]);
    }

    public function entradasPorDefecto(): array
    {
        return ['monto_anual_unidad' => 0];
    }

    public function reglas(): array
    {
        return [
            'monto_anual_unidad' => ['required', 'numeric', 'min:0'],
        ];
    }
}
