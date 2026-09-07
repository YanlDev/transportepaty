<?php

namespace App\Services\Costos;

/**
 * La tasa de un componente y el camino que se recorrió para llegar a ella.
 *
 * Los pasos no son adorno: una tarifa se discute, y poder abrir «mano de obra
 * directa» y ver sueldo mensual → costo anual → dividido entre los días que la
 * unidad está disponible es lo que separa un número defendible de uno que hay
 * que creer.
 */
final readonly class Derivacion
{
    /**
     * @param  list<array{etiqueta: string, valor: float, formato: string}>  $pasos
     */
    public function __construct(
        public float $tasa,
        public array $pasos,
    ) {}

    /**
     * @return array{tasa: float, pasos: list<array{etiqueta: string, valor: float, formato: string}>}
     */
    public function toArray(): array
    {
        return [
            'tasa' => $this->tasa,
            'pasos' => $this->pasos,
        ];
    }
}
