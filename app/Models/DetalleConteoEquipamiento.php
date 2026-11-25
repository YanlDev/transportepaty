<?php

namespace App\Models;

use App\Models\Concerns\RegistraHistorial;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Cuántas buenas y cuántas dañadas de un ítem en un conteo, con la nota y
 * las fotos de lo que se vio.
 *
 * @property int $id
 * @property int $conteo_equipamiento_id
 * @property int $item_equipamiento_id
 * @property int $buenas
 * @property int $danadas
 * @property string|null $nota
 * @property-read ItemEquipamiento $item
 */
#[Fillable(['conteo_equipamiento_id', 'item_equipamiento_id', 'buenas', 'danadas', 'nota'])]
class DetalleConteoEquipamiento extends Model implements HasMedia
{
    use InteractsWithMedia, RegistraHistorial;

    /** Cuántas fotos admite un ítem en un levantamiento. */
    public const MAXIMO_FOTOS = 3;

    protected $table = 'detalles_conteo_equipamiento';

    /**
     * @return BelongsTo<ItemEquipamiento, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(ItemEquipamiento::class, 'item_equipamiento_id');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('fotos')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
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
