<?php

namespace App\Services\Costos;

use App\Models\ParametroFlota;

/**
 * Costo por kilómetro de servicios que se repiten cada tanto kilometraje, cada
 * uno con su propia frecuencia: el preventivo cada 17.500 km, el correctivo
 * cada 100.000, la mano de obra de cada servicio.
 *
 * Acá sí se suman los costos por kilómetro de cada línea, porque los servicios
 * corren en paralelo a lo largo de la vida de la unidad —a diferencia de
 * `MetodoCicloVida`, donde las etapas son sucesivas—.
 */
class MetodoFrecuenciaKm implements Metodo
{
    use LeeEntradas;

    public function derivar(array $entradas, ParametroFlota $flota): Derivacion
    {
        $tasa = 0.0;
        $pasos = [];

        foreach ($this->filas($entradas, 'servicios') as $servicio) {
            $porKm = $this->dividir(
                $this->numero($servicio, 'costo'),
                $this->numero($servicio, 'cada_km'),
            );

            $tasa += $porKm;
            $pasos[] = $this->paso($this->texto($servicio['concepto'] ?? null, 'Servicio'), $porKm);
        }

        return new Derivacion($tasa, $pasos);
    }

    public function entradasPorDefecto(): array
    {
        return ['servicios' => [['concepto' => '', 'costo' => 0, 'cada_km' => 0]]];
    }

    public function reglas(): array
    {
        return [
            'servicios' => ['required', 'array', 'min:1'],
            'servicios.*.concepto' => ['nullable', 'string', 'max:100'],
            'servicios.*.costo' => ['required', 'numeric', 'min:0'],
            'servicios.*.cada_km' => ['required', 'numeric', 'min:1'],
        ];
    }
}
