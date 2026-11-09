<?php

namespace App\Models;

use App\Models\Concerns\RegistraHistorial;
use Database\Factories\UbicacionAlmacenFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un estante o zona del almacén («A-3», «Patio de llantas»). Ordena el
 * stock y permite hacer los conteos estante por estante.
 *
 * @property int $id
 * @property string $nombre
 * @property string|null $descripcion
 */
#[Fillable(['nombre', 'descripcion'])]
class UbicacionAlmacen extends Model
{
    /** @use HasFactory<UbicacionAlmacenFactory> */
    use HasFactory, RegistraHistorial;

    protected $table = 'ubicaciones_almacen';

    /**
     * @return HasMany<Articulo, $this>
     */
    public function articulos(): HasMany
    {
        return $this->hasMany(Articulo::class);
    }

    public function etiquetaHistorial(): ?string
    {
        return $this->nombre;
    }
}
