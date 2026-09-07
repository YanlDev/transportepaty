<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Un distrito del Perú con su código INEI de 6 dígitos.
 *
 * La guía de remisión exige ese código para el punto de partida y el de
 * llegada, y no se puede adivinar: Antauta es 210802 y no 210902, porque
 * Melgar es la provincia 08 de Puno. Tenerlo como catálogo evita que alguien
 * lo tipee de memoria y SUNAT rechace la guía.
 *
 * @property string $codigo
 * @property string $distrito
 * @property string $provincia
 * @property string $departamento
 * @property string $busqueda
 */
#[Fillable(['codigo', 'distrito', 'provincia', 'departamento', 'busqueda'])]
class Ubigeo extends Model
{
    protected $primaryKey = 'codigo';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    /**
     * Cómo se muestra al elegirlo: el distrito solo es ambiguo —hay varios
     * «San Juan»— así que va acompañado de su provincia y departamento.
     */
    public function etiqueta(): string
    {
        return "{$this->distrito}, {$this->provincia}, {$this->departamento}";
    }

    /**
     * Normaliza igual que la columna `busqueda`: mayúsculas y sin tildes, para
     * que «Antaúta» y «antauta» encuentren lo mismo.
     */
    public static function normalizar(string $texto): string
    {
        return Str::upper(Str::ascii($texto));
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeBuscar(Builder $query, string $termino): Builder
    {
        $normalizado = self::normalizar(trim($termino));

        if ($normalizado === '') {
            return $query;
        }

        // Cada palabra debe aparecer, en cualquier orden: así «melgar puno»
        // y «puno melgar» llegan al mismo lugar.
        foreach (preg_split('/\s+/', $normalizado) ?: [] as $palabra) {
            $query->whereLike('busqueda', "%{$palabra}%");
        }

        return $query;
    }
}
