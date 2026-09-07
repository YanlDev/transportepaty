<?php

namespace App\Services\Costos;

use App\Models\ParametroFlota;

/**
 * Lo que cuesta tener el tracto y la carreta, aunque estén parados.
 *
 * Son dos cosas distintas sumadas: el valor que la unidad pierde con el uso
 * (depreciación hasta su valor residual) y lo que ese capital rendiría puesto
 * en otra cosa —o lo que cuesta el préstamo que lo financia—. Dejar fuera la
 * segunda es el error clásico que hace que una tarifa parezca rentable hasta
 * que hay que renovar la flota y no alcanza.
 */
class MetodoActivo implements Metodo
{
    use LeeEntradas;

    public function derivar(array $entradas, ParametroFlota $flota): Derivacion
    {
        $valor = $this->numero($entradas, 'valor_tracto') + $this->numero($entradas, 'valor_carreta');
        $residual = $this->numero($entradas, 'valor_residual_pct');
        $vida = $this->numero($entradas, 'vida_util_anios');
        $tasaAnual = $this->numero($entradas, 'tasa_anual');

        $depreciacion = $this->dividir($valor * (1 - $residual), $vida);
        // Sobre el capital promedio inmovilizado a lo largo de la vida útil,
        // no sobre el valor de compra: al final del período solo queda el
        // residual, y cobrar la tasa sobre el valor inicial durante todos los
        // años infla el costo.
        $capitalPromedio = $valor * (1 + $residual) / 2;
        $costoCapital = $capitalPromedio * $tasaAnual;
        $anual = $depreciacion + $costoCapital;

        return new Derivacion($this->dividir($anual, $flota->diasDisponibles()), [
            $this->paso('Valor del conjunto (tracto + carreta)', $valor),
            $this->paso('Valor residual', $residual, 'porcentaje'),
            $this->paso('Vida útil', $vida, 'numero'),
            $this->paso('Depreciación anual', $depreciacion),
            $this->paso('Capital promedio inmovilizado', $capitalPromedio),
            $this->paso('Tasa anual del capital', $tasaAnual, 'porcentaje'),
            $this->paso('Costo del capital al año', $costoCapital),
            $this->paso('Costo anual del activo', $anual),
            $this->paso('Días disponibles por unidad', $flota->diasDisponibles(), 'dias'),
        ]);
    }

    public function entradasPorDefecto(): array
    {
        return [
            'valor_tracto' => 0,
            'valor_carreta' => 0,
            'valor_residual_pct' => 0.35,
            'vida_util_anios' => 5,
            'tasa_anual' => 0.1165,
        ];
    }

    public function reglas(): array
    {
        return [
            'valor_tracto' => ['required', 'numeric', 'min:0'],
            'valor_carreta' => ['required', 'numeric', 'min:0'],
            'valor_residual_pct' => ['required', 'numeric', 'min:0', 'max:1'],
            'vida_util_anios' => ['required', 'numeric', 'min:0.5'],
            'tasa_anual' => ['required', 'numeric', 'min:0', 'max:1'],
        ];
    }
}
