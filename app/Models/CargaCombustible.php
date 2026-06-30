<?php

namespace App\Models;

use Database\Factories\CargaCombustibleFactory;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property int $id
 * @property int $vehiculo_id
 * @property int|null $registrada_por
 * @property Carbon $fecha_carga
 * @property int|null $odometro
 * @property float|null $galones
 * @property float|null $costo_total
 * @property float|null $precio_por_galon
 * @property string|null $observaciones
 * @property int|null $procesada_por
 * @property Carbon|null $procesada_en
 * @property-read bool $procesada
 */
#[Fillable([
    'vehiculo_id',
    'registrada_por',
    'fecha_carga',
    'odometro',
    'galones',
    'costo_total',
    'precio_por_galon',
    'observaciones',
    'procesada_por',
    'procesada_en',
])]
#[Appends(['procesada'])]
class CargaCombustible extends Model implements HasMedia
{
    /** @use HasFactory<CargaCombustibleFactory> */
    use HasFactory;

    use InteractsWithMedia;

    protected $table = 'cargas_combustible';

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
    public function registradaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrada_por');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function procesadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'procesada_por');
    }

    /**
     * A load is "processed" once an admin has filled the odometer and gallons;
     * only processed loads take part in the efficiency calculation.
     *
     * @return Attribute<bool, never>
     */
    protected function procesada(): Attribute
    {
        return Attribute::get(fn (): bool => $this->procesada_en !== null);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('comprobante')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);

        $this->addMediaCollection('odometro_foto')
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
            'fecha_carga' => 'datetime',
            'odometro' => 'integer',
            'galones' => 'decimal:3',
            'costo_total' => 'decimal:2',
            'precio_por_galon' => 'decimal:3',
            'procesada_en' => 'datetime',
        ];
    }
}
