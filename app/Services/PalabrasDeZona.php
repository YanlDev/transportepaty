<?php

namespace App\Services;

use App\Models\PalabraZona;
use Throwable;

/**
 * Las palabras que reconocen cada zona en las direcciones de las GR, leídas
 * una sola vez por petición: armar el historial lee cientos de GR y no tiene
 * sentido consultar la tabla en cada una.
 *
 * Si la tabla no se puede leer (antes de migrar, o en un test unitario sin
 * base) se usan las de siempre.
 */
class PalabrasDeZona
{
    /** @var array<string, list<string>>|null */
    private ?array $palabras = null;

    /**
     * @param  array<string, list<string>>  $deSiempre
     * @return array<string, list<string>>
     */
    public function todas(array $deSiempre): array
    {
        if ($this->palabras !== null) {
            return $this->palabras;
        }

        $leidas = [];

        try {
            foreach (PalabraZona::query()->orderBy('id')->get(['zona', 'palabra']) as $palabra) {
                $leidas[$palabra->zona->value][] = $palabra->palabra;
            }
        } catch (Throwable) {
            return $deSiempre;
        }

        return $this->palabras = $leidas === [] ? $deSiempre : $leidas;
    }

    /** Después de editar las palabras, para que se lean de nuevo. */
    public function olvidar(): void
    {
        $this->palabras = null;
    }
}
