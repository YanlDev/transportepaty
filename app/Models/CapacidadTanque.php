<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Cuántos galones caben en el tanque de los tractos de una marca. Sirve para
 * avisar de un voucher que no cabe (un error de tipeo, 2500 por 250), sin
 * bloquearlo: lo confirma quien lo registra.
 *
 * @property int $id
 * @property string $marca
 * @property string $galones
 */
#[Fillable(['marca', 'galones'])]
class CapacidadTanque extends Model
{
    protected $table = 'capacidades_tanque';

    /**
     * La capacidad de la marca de una unidad; null si no se cargó.
     */
    public static function de(?string $marca): ?float
    {
        if (blank($marca)) {
            return null;
        }

        $galones = self::query()->whereRaw('upper(marca) = ?', [mb_strtoupper(trim($marca))])->value('galones');

        return $galones === null ? null : (float) $galones;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'galones' => 'decimal:1',
        ];
    }
}
