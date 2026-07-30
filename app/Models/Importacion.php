<?php

namespace App\Models;

use Database\Factories\ImportacionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Un Excel de disponibilidad subido para un día. Antes de confirmar es una
 * previsualización nada más: las filas quedan resueltas contra el catálogo pero
 * no tocan el estado canónico hasta que alguien lo aprueba. El archivo original
 * se conserva siempre, sea cual sea el resultado, como evidencia de lo que
 * llegó.
 *
 * @property int $id
 * @property Carbon $fecha
 * @property string $archivo_original
 * @property int $subido_por
 * @property Carbon|null $confirmado_en
 * @property int $filas_totales
 * @property int $filas_resueltas
 * @property-read User $usuario
 * @property-read Collection<int, ImportacionFila> $filas
 */
#[Fillable([
    'fecha',
    'archivo_original',
    'subido_por',
])]
class Importacion extends Model implements HasMedia
{
    /** @use HasFactory<ImportacionFactory> */
    use HasFactory, InteractsWithMedia;

    protected $table = 'importaciones';

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('archivo')
            ->singleFile()
            ->acceptsMimeTypes([
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
    }

    public function estaConfirmada(): bool
    {
        return $this->confirmado_en !== null;
    }

    public function marcarConfirmada(): void
    {
        $this->confirmado_en = Carbon::now();
        $this->save();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por');
    }

    /**
     * @return HasMany<ImportacionFila, $this>
     */
    public function filas(): HasMany
    {
        return $this->hasMany(ImportacionFila::class)->orderBy('numero_fila');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha' => 'date:Y-m-d',
            'confirmado_en' => 'datetime',
        ];
    }
}
