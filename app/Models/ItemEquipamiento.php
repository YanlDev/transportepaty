<?php

namespace App\Models;

use App\Enums\AplicaEquipamiento;
use App\Models\Concerns\RegistraHistorial;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Algo que lleva la carreta y se cuenta en base: una faja de 4" con
 * chicharra, una de 2" sin chicharra… El mínimo es el mismo para todas las
 * carretas; sin mínimo, el ítem se cuenta pero no avisa faltantes.
 *
 * @property int $id
 * @property string $nombre
 * @property int|null $articulo_id
 * @property AplicaEquipamiento $aplica_a
 * @property bool $lleva_vencimiento
 * @property int|null $minimo
 * @property int $orden
 * @property bool $activo
 * @property-read Articulo|null $articulo
 */
#[Fillable(['nombre', 'articulo_id', 'aplica_a', 'lleva_vencimiento', 'minimo', 'orden', 'activo'])]
class ItemEquipamiento extends Model
{
    use RegistraHistorial;

    protected $table = 'items_equipamiento';

    /**
     * El artículo del almacén con que se entrega este ítem.
     *
     * @return BelongsTo<Articulo, $this>
     */
    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class);
    }

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
            'aplica_a' => AplicaEquipamiento::class,
            'lleva_vencimiento' => 'boolean',
            'minimo' => 'integer',
            'orden' => 'integer',
            'activo' => 'boolean',
        ];
    }
}
