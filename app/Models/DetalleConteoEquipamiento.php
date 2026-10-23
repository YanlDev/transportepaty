<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cuántas buenas y cuántas dañadas de un ítem en un conteo.
 *
 * @property int $id
 * @property int $conteo_equipamiento_id
 * @property int $item_equipamiento_id
 * @property int $buenas
 * @property int $danadas
 * @property-read ItemEquipamiento $item
 */
#[Fillable(['conteo_equipamiento_id', 'item_equipamiento_id', 'buenas', 'danadas'])]
class DetalleConteoEquipamiento extends Model
{
    protected $table = 'detalles_conteo_equipamiento';

    /**
     * @return BelongsTo<ItemEquipamiento, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(ItemEquipamiento::class, 'item_equipamiento_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'buenas' => 'integer',
            'danadas' => 'integer',
        ];
    }
}
