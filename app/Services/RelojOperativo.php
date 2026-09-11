<?php

namespace App\Services;

use Carbon\CarbonImmutable;

/**
 * El día que es «hoy» para la operación.
 *
 * La aplicación guarda y calcula todo en UTC, que es lo que recomienda Laravel
 * y lo que evita sorpresas al serializar. Pero la operación es peruana
 * (UTC-5), y hay decisiones que no dependen de un instante sino del día del
 * calendario: con qué fecha se emite una factura, cuántos días lleva sin
 * cobrarse, qué día del mes se va para la meta de concentrado, en qué fecha se
 * da de baja a un conductor.
 *
 * Tomadas de `now()` a secas, todas esas quedaban corridas un día entre las
 * 19:00 y la medianoche hora de Lima, que es justamente el rato en que se
 * cierra la jornada y se registra lo del día.
 *
 * Hay dos formas de leer ese día y no son intercambiables:
 *
 * - `ahora()` da el instante en hora de Lima. Sirve para preguntarle el número
 *   de día del mes, cuántos días tiene el mes, el nombre del mes.
 * - `fechaDeHoy()` da ese mismo día a medianoche pero en la zona de la
 *   aplicación. Es el que va contra una columna casteada a `date`, porque esos
 *   valores vuelven de la base a medianoche UTC: comparar contra medianoche de
 *   Lima (05:00 UTC) daría por vencido lo que vence hoy.
 *
 * Para instantes —`created_at`, marcas de tiempo— se sigue usando `now()`:
 * esos no tienen el problema porque no se recortan a un día.
 */
class RelojOperativo
{
    /**
     * Ahora mismo, leído en la zona horaria de la operación. Para preguntarle
     * partes del calendario (día del mes, días del mes), no para comparar
     * contra fechas guardadas.
     */
    public static function ahora(): CarbonImmutable
    {
        return CarbonImmutable::now(self::zona());
    }

    /**
     * El día de calendario en curso para la operación, como `Y-m-d`. Es lo que
     * corresponde guardar en una columna `date`.
     */
    public static function hoy(): string
    {
        return self::ahora()->toDateString();
    }

    /**
     * El día de hoy a medianoche, en la zona de la aplicación. Este es el que
     * se compara contra atributos casteados a `date`.
     */
    public static function fechaDeHoy(): CarbonImmutable
    {
        return CarbonImmutable::parse(self::hoy());
    }

    /**
     * Primer día del mes en curso, en el mismo marco que `fechaDeHoy()`.
     */
    public static function inicioDelMes(): CarbonImmutable
    {
        return self::fechaDeHoy()->startOfMonth();
    }

    /**
     * Último día del mes en curso, en el mismo marco que `fechaDeHoy()`.
     */
    public static function finDelMes(): CarbonImmutable
    {
        return self::fechaDeHoy()->endOfMonth();
    }

    private static function zona(): string
    {
        return config()->string('app.business_timezone');
    }
}
