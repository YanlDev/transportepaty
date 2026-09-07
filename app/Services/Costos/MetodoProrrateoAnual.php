<?php

namespace App\Services\Costos;

use App\Models\ParametroFlota;

/**
 * Un gasto que se paga por año y por toda la flota junta —los sueldos del
 * taller, los seguros, el alquiler de la base— repartido entre los días-unidad
 * que la flota puede vender en el año.
 *
 * La dedicación existe porque parte de esa estructura atiende otras cosas
 * además de la operación de transporte: el Excel prorratea la mano de obra
 * indirecta al 75% por eso mismo.
 */
class MetodoProrrateoAnual implements Metodo
{
    use LeeEntradas;

    public function derivar(array $entradas, ParametroFlota $flota): Derivacion
    {
        $montoAnual = $this->numero($entradas, 'monto_anual');
        $dedicacion = $this->numero($entradas, 'dedicacion_pct', 1.0);

        $asignado = $montoAnual * $dedicacion;
        $diasUnidad = $flota->tamano_flota * $flota->diasDisponibles();

        return new Derivacion($this->dividir($asignado, $diasUnidad), [
            $this->paso('Monto anual', $montoAnual),
            $this->paso('Dedicación a la operación', $dedicacion, 'porcentaje'),
            $this->paso('Asignado a la operación', $asignado),
            $this->paso('Unidades de la flota', (float) $flota->tamano_flota, 'numero'),
            $this->paso('Días disponibles por unidad', $flota->diasDisponibles(), 'dias'),
            $this->paso('Días-unidad al año', $diasUnidad, 'numero'),
        ]);
    }

    public function entradasPorDefecto(): array
    {
        return ['monto_anual' => 0, 'dedicacion_pct' => 1];
    }

    public function reglas(): array
    {
        return [
            'monto_anual' => ['required', 'numeric', 'min:0'],
            'dedicacion_pct' => ['required', 'numeric', 'min:0', 'max:1'],
        ];
    }
}
