<?php

namespace App\Models;

use App\Enums\TipoDocumento;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property int $vehiculo_id
 * @property TipoDocumento $tipo
 * @property string|null $nombre
 * @property string|null $numero
 * @property Carbon|null $fecha_emision
 * @property Carbon|null $fecha_vencimiento
 * @property string|null $observaciones
 */
#[Fillable([
    'vehiculo_id',
    'tipo',
    'nombre',
    'numero',
    'fecha_emision',
    'fecha_vencimiento',
    'observaciones',
])]
class VehiculoDocumento extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'vehiculo_documentos';

    /**
     * @return BelongsTo<Vehiculo, $this>
     */
    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('archivo')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf']);
    }

    /**
     * Representación para el frontend.
     *
     * @return array<string, mixed>
     */
    public function toFrontArray(): array
    {
        $media = $this->getFirstMedia('archivo');

        return [
            'id' => $this->id,
            'tipo' => $this->tipo->value,
            'tipo_label' => $this->tipo->label(),
            'nombre' => $this->nombre,
            'numero' => $this->numero,
            'fecha_emision' => $this->fecha_emision?->format('Y-m-d'),
            'fecha_vencimiento' => $this->fecha_vencimiento?->format('Y-m-d'),
            'url' => $media?->getUrl() ?? '',
            'es_pdf' => $media?->mime_type === 'application/pdf',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoDocumento::class,
            'fecha_emision' => 'date:Y-m-d',
            'fecha_vencimiento' => 'date:Y-m-d',
        ];
    }
}
