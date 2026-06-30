<?php

namespace App\Models;

use Database\Factories\MantenimientoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property int $id
 * @property int $vehiculo_id
 * @property int|null $registrado_por
 * @property int|null $conductor_id
 * @property Carbon $fecha_realizado
 * @property int $odometro
 * @property string|null $proveedor
 * @property string|null $factura_numero
 * @property float|null $costo_total
 * @property string|null $observaciones
 */
#[Fillable([
    'vehiculo_id',
    'registrado_por',
    'conductor_id',
    'fecha_realizado',
    'odometro',
    'proveedor',
    'factura_numero',
    'costo_total',
    'observaciones',
])]
class Mantenimiento extends Model implements HasMedia
{
    /** @use HasFactory<MantenimientoFactory> */
    use HasFactory;

    use InteractsWithMedia;
    use SoftDeletes;

    protected $table = 'mantenimientos';

    /**
     * @return BelongsTo<Vehiculo, $this>
     */
    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    /**
     * @return BelongsTo<Conductor, $this>
     */
    public function conductor(): BelongsTo
    {
        return $this->belongsTo(Conductor::class);
    }

    /**
     * @return HasMany<MantenimientoItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(MantenimientoItem::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('comprobante')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf']);

        $this->addMediaCollection('fotos')
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
            'fecha_realizado' => 'datetime',
            'odometro' => 'integer',
            'costo_total' => 'decimal:2',
        ];
    }
}
