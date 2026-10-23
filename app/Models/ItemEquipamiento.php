<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Algo que lleva la carreta y se cuenta en base: una faja de 4" con
 * chicharra, una de 2" sin chicharra… El mínimo es el mismo para todas las
 * carretas; sin mínimo, el ítem se cuenta pero no avisa faltantes.
 *
 * @property int $id
 * @property string $nombre
 * @property int|null $minimo
 * @property int $orden
 * @property bool $activo
 */
#[Fillable(['nombre', 'minimo', 'orden', 'activo'])]
class ItemEquipamiento extends Model
{
    protected $table = 'items_equipamiento';

    /**
     * Los que se cuentan hoy, en el orden del catálogo.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVigentes(Builder $query): void
    {
        $query->where('activo', true)->orderBy('orden')->orderBy('id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'minimo' => 'integer',
            'orden' => 'integer',
            'activo' => 'boolean',
        ];
    }
}
