<?php

namespace App\Models;

use App\Enums\PosicionFoto;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property int $id
 * @property int $vehiculo_id
 * @property PosicionFoto $posicion
 * @property int $orden
 */
#[Fillable(['vehiculo_id', 'posicion', 'orden'])]
class VehiculoFoto extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'vehiculo_fotos';

    /**
     * @return BelongsTo<Vehiculo, $this>
     */
    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('imagen')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->nonQueued()
            ->width(400)
            ->height(300);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'posicion' => PosicionFoto::class,
            'orden' => 'integer',
        ];
    }
}
