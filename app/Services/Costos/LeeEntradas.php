<?php

namespace App\Services\Costos;

/**
 * Las entradas de un componente llegan de un JSON que alguien editó en una
 * pantalla: un campo puede venir vacío, en blanco o directamente no estar.
 * Un método de cálculo no debería tumbar la pantalla de parámetros por eso
 * —muestra la tasa que da con lo que hay— así que la lectura es tolerante y
 * el formulario es quien exige los campos obligatorios.
 */
trait LeeEntradas
{
    /**
     * @param  array<string, mixed>  $entradas
     */
    protected function numero(array $entradas, string $clave, float $porDefecto = 0.0): float
    {
        $valor = $entradas[$clave] ?? null;

        return is_numeric($valor) ? (float) $valor : $porDefecto;
    }

    /**
     * @param  array<string, mixed>  $entradas
     * @return list<array<string, mixed>>
     */
    protected function filas(array $entradas, string $clave): array
    {
        $filas = $entradas[$clave] ?? [];

        if (! is_array($filas)) {
            return [];
        }

        return array_values(array_filter($filas, is_array(...)));
    }

    protected function texto(mixed $valor, string $porDefecto = ''): string
    {
        return is_string($valor) && $valor !== '' ? $valor : $porDefecto;
    }

    /**
     * @return array{etiqueta: string, valor: float, formato: string}
     */
    protected function paso(string $etiqueta, float $valor, string $formato = 'moneda'): array
    {
        return ['etiqueta' => $etiqueta, 'valor' => $valor, 'formato' => $formato];
    }

    protected function dividir(float $dividendo, float $divisor): float
    {
        return $divisor > 0.0 ? $dividendo / $divisor : 0.0;
    }
}
