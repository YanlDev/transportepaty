<?php

namespace App\Services\Costos;

use App\Models\ParametroFlota;

/**
 * Lo que cuesta un día de conductor, desde el sueldo que figura en la boleta
 * hasta el costo real para la empresa.
 *
 * Dos cosas que sorprenden si uno mira solo el sueldo: entre gratificaciones,
 * vacaciones, CTS y EsSalud el costo anual es del orden de 17 sueldos, no de
 * 12; y hace falta más de un chofer por camión —la unidad rueda más días de
 * los que una persona puede manejar— así que el costo se multiplica por esa
 * relación antes de repartirlo entre los días que la unidad está disponible.
 */
class MetodoPlanillaConductor implements Metodo
{
    use LeeEntradas;

    public function derivar(array $entradas, ParametroFlota $flota): Derivacion
    {
        $mensual = $this->numero($entradas, 'sueldo_base')
            + $this->numero($entradas, 'seguro_vida_ley')
            + $this->numero($entradas, 'sctr')
            + $this->numero($entradas, 'asignacion_familiar')
            + $this->numero($entradas, 'otros');

        $remuneraciones = $mensual * $this->numero($entradas, 'remuneraciones_anuales');
        $vacaciones = $mensual * $this->numero($entradas, 'meses_vacaciones');
        $gratificaciones = $mensual * $this->numero($entradas, 'gratificaciones');
        $cts = $mensual * $this->numero($entradas, 'cts_meses');

        // EsSalud sobre remuneraciones, vacaciones y gratificaciones, que es
        // como lo calcula la planilla de la casa.
        $baseEsSalud = $remuneraciones + $vacaciones + $gratificaciones;
        $esSalud = $baseEsSalud * $this->numero($entradas, 'essalud_pct');

        $diasFeriados = $this->numero($entradas, 'dias_feriados');
        $feriados = $diasFeriados * $this->numero($entradas, 'valor_dia_feriado');

        $anual = $remuneraciones + $vacaciones + $gratificaciones + $cts + $esSalud + $feriados;
        $choferesPorCamion = $this->numero($entradas, 'choferes_por_camion', 1.0);
        $porCamion = $anual * $choferesPorCamion;

        return new Derivacion($this->dividir($porCamion, $flota->diasDisponibles()), [
            $this->paso('Total mensual (sueldo + vida ley + SCTR + asignación)', $mensual),
            $this->paso('Remuneraciones del año', $remuneraciones),
            $this->paso('Vacaciones', $vacaciones),
            $this->paso('Gratificaciones', $gratificaciones),
            $this->paso('CTS', $cts),
            $this->paso('EsSalud', $esSalud),
            $this->paso('Feriados', $feriados),
            $this->paso('Costo anual del conductor', $anual),
            $this->paso('Conductores por camión', $choferesPorCamion, 'numero'),
            $this->paso('Costo anual por camión', $porCamion),
            $this->paso('Días disponibles por unidad', $flota->diasDisponibles(), 'dias'),
        ]);
    }

    public function entradasPorDefecto(): array
    {
        return [
            'sueldo_base' => 0,
            'seguro_vida_ley' => 0,
            'sctr' => 0,
            'asignacion_familiar' => 0,
            'otros' => 0,
            'remuneraciones_anuales' => 11,
            'meses_vacaciones' => 1,
            'gratificaciones' => 2,
            'cts_meses' => 1.1667,
            'essalud_pct' => 0.09,
            'dias_feriados' => 16,
            'valor_dia_feriado' => 0,
            'choferes_por_camion' => 1,
        ];
    }

    public function reglas(): array
    {
        return [
            'sueldo_base' => ['required', 'numeric', 'min:0'],
            'seguro_vida_ley' => ['required', 'numeric', 'min:0'],
            'sctr' => ['required', 'numeric', 'min:0'],
            'asignacion_familiar' => ['required', 'numeric', 'min:0'],
            'otros' => ['required', 'numeric', 'min:0'],
            'remuneraciones_anuales' => ['required', 'numeric', 'min:0'],
            'meses_vacaciones' => ['required', 'numeric', 'min:0'],
            'gratificaciones' => ['required', 'numeric', 'min:0'],
            'cts_meses' => ['required', 'numeric', 'min:0'],
            'essalud_pct' => ['required', 'numeric', 'min:0', 'max:1'],
            'dias_feriados' => ['required', 'numeric', 'min:0'],
            'valor_dia_feriado' => ['required', 'numeric', 'min:0'],
            'choferes_por_camion' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
