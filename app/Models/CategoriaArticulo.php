<?php

namespace App\Models;

use Database\Factories\CategoriaArticuloFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * El grupo de un artículo (filtros, lubricantes, frenos…): ordena el stock y
 * los reportes de consumo.
 *
 * @property int $id
 * @property string $nombre
 * @property string|null $clave
 */
#[Fillable(['nombre', 'clave'])]
class CategoriaArticulo extends Model
{
    /** @use HasFactory<CategoriaArticuloFactory> */
    use HasFactory;

    protected $table = 'categorias_articulo';

    /**
     * @return HasMany<Articulo, $this>
     */
    public function articulos(): HasMany
    {
        return $this->hasMany(Articulo::class);
    }
}
