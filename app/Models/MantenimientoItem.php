<?php

namespace App\Models;

use Database\Factories\MantenimientoItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $mantenimiento_id
 * @property int|null $plantilla_id
 * @property string $nombre
 * @property string $tipo_mantenimiento
 * @property float|null $costo
 */
#[Fillable([
    'mantenimiento_id',
    'plantilla_id',
    'nombre',
    'tipo_mantenimiento',
    'costo',
])]
class MantenimientoItem extends Model
{
    /** @use HasFactory<MantenimientoItemFactory> */
    use HasFactory;

    protected $table = 'mantenimiento_items';

    /**
     * @return BelongsTo<Mantenimiento, $this>
     */
    public function mantenimiento(): BelongsTo
    {
        return $this->belongsTo(Mantenimiento::class);
    }

    /**
     * @return BelongsTo<PlantillaMantenimiento, $this>
     */
    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(PlantillaMantenimiento::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'costo' => 'decimal:2',
        ];
    }
}
