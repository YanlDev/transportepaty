<?php

namespace App\Rules;

use App\Services\PeriodoAlmacen;
use App\Services\RelojOperativo;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;

/**
 * La fecha de un movimiento del almacén: del mes en curso (los anteriores
 * están cerrados) y no futura. «Hoy» es el de Lima, no el del servidor: de
 * noche ya sería mañana en UTC.
 */
class FechaDelAlmacen implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $fecha = Carbon::parse((string) $value)->startOfDay();

        if ($fecha->gt(RelojOperativo::fechaDeHoy())) {
            $fail('La fecha no puede ser futura.');

            return;
        }

        if (! PeriodoAlmacen::estaAbierto($fecha)) {
            $fail(PeriodoAlmacen::mensajeCerrado($fecha));
        }
    }
}
