<?php

namespace App\Models;

use App\Models\Concerns\RegistraHistorial;
use Database\Factories\PiezaEquipamientoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Una pieza de equipamiento con código propio: este extintor, este
 * botiquín. Dice cuál es cada una de las que la unidad tiene asignadas, qué
 * es y cuándo vence. Dada de baja, queda con su código para el historial.
 *
 * @property int $id
 * @property string $codigo
 * @property int $item_equipamiento_id
 * @property int|null $vehiculo_id
 * @property string|null $descripcion
 * @property string|null $serie
 * @property Carbon|null $vence_el
 * @property int|null $conteo_equipamiento_id
 * @property Carbon|null $baja_at
 * @property int|null $user_id
 * @property-read ItemEquipamiento $item
 * @property-read Vehiculo|null $vehiculo
 */
#[Fillable(['codigo', 'item_equipamiento_id', 'vehiculo_id', 'descripcion', 'serie', 'vence_el', 'conteo_equipamiento_id', 'baja_at', 'user_id'])]
class PiezaEquipamiento extends Model
{
    /** @use HasFactory<PiezaEquipamientoFactory> */
    use HasFactory, RegistraHistorial;

    protected $table = 'piezas_equipamiento';

    /**
     * @return BelongsTo<ItemEquipamiento, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(ItemEquipamiento::class, 'item_equipamiento_id');
    }

    /**
     * @return BelongsTo<Vehiculo, $this>
     */
    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class)->withTrashed();
    }

    /**
     * Las que siguen en una unidad: sin dar de baja.
     *
     * @param  Builder<self>  $query
     */
    public function scopeEnUso(Builder $query): void
    {
        $query->whereNull('baja_at')->whereNotNull('vehiculo_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'vence_el' => 'date',
            'baja_at' => 'datetime',
        ];
    }
}
