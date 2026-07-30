<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nombre alternativo con el que una ubicación aparece en los reportes. Cada
 * alias nace de una confirmación tuya sobre un texto que el sistema no supo
 * resolver, de modo que el catálogo aprende y deja de preguntar por lo mismo.
 *
 * @property int $id
 * @property int $ubicacion_id
 * @property string $nombre_normalizado
 * @property-read Ubicacion $ubicacion
 */
#[Fillable([
    'ubicacion_id',
    'nombre_normalizado',
])]
class UbicacionAlias extends Model
{
    protected $table = 'ubicacion_alias';

    /**
     * @return BelongsTo<Ubicacion, $this>
     */
    public function ubicacion(): BelongsTo
    {
        return $this->belongsTo(Ubicacion::class);
    }
}
