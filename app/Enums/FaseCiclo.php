<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Tramo del circuito cerrado en el que se encuentra la unidad. La vuelta
 * completa toma del orden de 10 a 14 días y siempre recorre las mismas etapas,
 * así que la fase por sí sola ya dice bastante de dónde está y qué le falta.
 *
 * El circuito no es una fila recta: al llegar a Pisco el cliente decide el
 * retorno, y de ahí la unidad puede volver cargada a mina —y encadenar otra
 * bajada con concentrado— o quedarse en Lima a buscar carga particular. Esa
 * bifurcación es la razón de que las transiciones se declaren como lista y no
 * como una simple fase siguiente.
 */
enum FaseCiclo: string
{
    use HasLabel;

    case SubidaMina = 'subida_mina';
    case MinaPisco = 'mina_pisco';
    case RetornoPisco = 'retorno_pisco';
    case LimaJuliaca = 'lima_juliaca';

    public function label(): string
    {
        return match ($this) {
            self::SubidaMina => 'Subida a mina',
            self::MinaPisco => 'San Rafael → Pisco',
            self::RetornoPisco => 'Retorno desde Pisco',
            self::LimaJuliaca => 'Lima → Juliaca',
        };
    }

    /**
     * Descripción de lo que hace la unidad en esta fase, para las pantallas
     * donde la etiqueta corta no basta.
     */
    public function descripcion(): string
    {
        return match ($this) {
            self::SubidaMina => 'Sube desde zona base a U.M. San Rafael a cargar concentrado.',
            self::MinaPisco => 'Baja con concentrado por la ruta troncal hacia Pisco.',
            self::RetornoPisco => 'Retorna desde Pisco: metálico a Callao, o escoria y materiales a mina.',
            self::LimaJuliaca => 'Vuelve a base con carga particular de terceros.',
        };
    }

    /**
     * Fases a las que la unidad puede pasar cuando deja esta. No incluye
     * quedarse donde está, que siempre está permitido y contempla
     * `puedeTransicionarA()`.
     *
     * Desde el retorno hay dos salidas legítimas: si volvió cargada a San
     * Rafael encadena otra bajada con concentrado, y si descargó metálico en
     * el Callao se queda en Lima esperando carga particular.
     *
     * @return array<int, self>
     */
    public function transicionesValidas(): array
    {
        return match ($this) {
            self::SubidaMina => [self::MinaPisco],
            self::MinaPisco => [self::RetornoPisco],
            self::RetornoPisco => [self::MinaPisco, self::LimaJuliaca],
            self::LimaJuliaca => [self::SubidaMina],
        };
    }

    /**
     * Indica si la unidad puede pasar de esta fase a la indicada. Quedarse en
     * la misma fase siempre vale: un tramo dura días y entre un reporte y el
     * siguiente lo normal es no haber cambiado de etapa.
     *
     * Lo que esto descarta son los saltos que se comen una etapa entera, como
     * pasar de bajar con concentrado a andar con carga particular sin haber
     * pasado nunca por el retorno de Pisco.
     */
    public function puedeTransicionarA(self $siguiente): bool
    {
        return $siguiente === $this
            || in_array($siguiente, $this->transicionesValidas(), true);
    }
}
