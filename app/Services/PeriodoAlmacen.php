<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * El almacén cierra el último día de cada mes. Desde el día 1 el mes anterior
 * ya está reportado a contabilidad: no se registra ni se anula nada con fecha
 * de un mes cerrado, porque cambiaría el costo de lo que ya se informó.
 *
 * Abierto queda solo el mes en curso, del día 1 a hoy (en hora de Lima).
 */
class PeriodoAlmacen
{
    public static function inicio(): CarbonImmutable
    {
        return RelojOperativo::inicioDelMes();
    }

    public static function estaAbierto(CarbonInterface|string $fecha): bool
    {
        return Carbon::parse($fecha)->startOfDay()->gte(self::inicio());
    }

    /**
     * «octubre de 2026 ya está cerrado: …».
     */
    public static function mensajeCerrado(CarbonInterface|string $fecha): string
    {
        $mes = Carbon::parse($fecha)->settings(['locale' => 'es'])->translatedFormat('F \d\e Y');

        return "{$mes} ya está cerrado: el almacén cierra el último día de cada mes. Usa una fecha desde el ".self::inicio()->format('d/m/Y').'.';
    }

    /**
     * Para anular o cerrar un documento ya registrado.
     *
     * @throws ValidationException
     */
    public static function exigirAbierto(CarbonInterface|string $fecha, string $campo = 'motivo'): void
    {
        if (! self::estaAbierto($fecha)) {
            throw ValidationException::withMessages([$campo => self::mensajeCerrado($fecha)]);
        }
    }
}
