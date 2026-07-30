<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * El resultado de leer una hoja de Excel: en qué fila está la cabecera, qué
 * etiqueta tiene cada columna, y el valor crudo de cada celda por fila.
 *
 * Guarda las etiquetas de cabecera en vez de asumir posiciones fijas de
 * columna, porque el mismo reporte ha llegado con columnas corridas entre un
 * mes y otro: detectar por nombre es lo que hace que el lector no dependa de
 * que nadie mueva una columna.
 */
final readonly class HojaExcelLeida
{
    /**
     * @param  array<string, string>  $columnas  Letra de columna => etiqueta de cabecera.
     * @param  array<int, array<string, string>>  $filas  Número de fila => [letra => valor crudo].
     */
    public function __construct(
        public int $filaCabecera,
        public array $columnas,
        public array $filas,
    ) {}

    /**
     * Letra de columna cuya cabecera coincide con la etiqueta indicada, o null
     * si el reporte no la trae. La comparación ignora tildes, mayúsculas y
     * espacios de más.
     */
    public function columnaDe(string $etiqueta): ?string
    {
        $buscada = self::normalizar($etiqueta);

        foreach ($this->columnas as $letra => $columna) {
            if (self::normalizar($columna) === $buscada) {
                return $letra;
            }
        }

        return null;
    }

    /**
     * Valor crudo de una columna en una fila, o null si la celda está vacía o
     * la columna no existe en este reporte.
     */
    public function valor(int $numeroFila, string $etiqueta): ?string
    {
        $letra = $this->columnaDe($etiqueta);

        if ($letra === null) {
            return null;
        }

        return $this->filas[$numeroFila][$letra] ?? null;
    }

    /**
     * Filas de datos: las que tienen algo en la columna CODIGO. Las que no,
     * son huecos del Excel y no unidades a importar.
     *
     * @return list<int>
     */
    public function numerosDeFilaConDatos(): array
    {
        $codigo = $this->columnaDe('CODIGO');

        if ($codigo === null) {
            return [];
        }

        $numeros = [];

        foreach ($this->filas as $numero => $fila) {
            if ($numero > $this->filaCabecera && ($fila[$codigo] ?? '') !== '') {
                $numeros[] = $numero;
            }
        }

        return $numeros;
    }

    private static function normalizar(string $texto): string
    {
        $sinPuntuacion = preg_replace('/[^A-Za-z0-9]+/', ' ', Str::ascii($texto)) ?? '';

        return mb_strtoupper(trim($sinPuntuacion));
    }
}
