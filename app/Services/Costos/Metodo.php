<?php

namespace App\Services\Costos;

use App\Models\ParametroFlota;

/**
 * Una forma de llegar a la tasa de un componente de costo a partir de sus
 * entradas. Cada implementación es una hoja del Excel de costos de la casa.
 *
 * Las entradas llegan como el array crudo que guarda el componente: cada
 * método sabe qué claves espera y responde por sus faltantes, porque el juego
 * de campos cambia por completo entre uno y otro (una planilla no se parece en
 * nada a un ciclo de neumáticos).
 */
interface Metodo
{
    /**
     * @param  array<string, mixed>  $entradas
     */
    public function derivar(array $entradas, ParametroFlota $flota): Derivacion;

    /**
     * Las entradas con las que un componente nuevo de este método arranca,
     * para que el formulario nunca abra con campos vacíos.
     *
     * @return array<string, mixed>
     */
    public function entradasPorDefecto(): array;

    /**
     * Las reglas de validación de sus entradas, relativas a la raíz de
     * `entradas`. Viven junto al cálculo y no en el formulario porque son parte
     * del método: quien sabe que un rendimiento no puede ser cero es quien
     * divide entre él.
     *
     * @return array<string, array<int, string>>
     */
    public function reglas(): array;
}
