<?php

namespace App\Models;

use App\Enums\TipoMovimientoEquipamiento;
use App\Models\Concerns\RegistraHistorial;
use App\Models\Concerns\SeAnula;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Un cambio en el equipamiento de una unidad que no pasa por el almacén: su
 * saldo inicial, un traslado a otra unidad o una baja. No se borra: se anula.
 *
 * @property int $id
 * @property int $vehiculo_id
 * @property int $item_equipamiento_id
 * @property TipoMovimientoEquipamiento $tipo
 * @property int $cantidad
 * @property Carbon $fecha
 * @property int|null $vehiculo_destino_id
 * @property int|null $conteo_equipamiento_id
 * @property string|null $motivo
 * @property int|null $user_id
 * @property Carbon|null $anulado_at
 * @property Carbon|null $created_at
 * @property-read ItemEquipamiento $item
 * @property-read Vehiculo $vehiculo
 * @property-read Vehiculo|null $destino
 * @property-read User|null $user
 */
#[Fillable([
    'vehiculo_id', 'item_equipamiento_id', 'tipo', 'cantidad', 'fecha', 'vehiculo_destino_id',
    'conteo_equipamiento_id', 'motivo', 'user_id', 'anulado_at', 'anulado_por', 'motivo_anulacion',
])]
class MovimientoEquipamiento extends Model
{
    use RegistraHistorial, SeAnula;

    protected $table = 'movimientos_equipamiento';

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
     * @return BelongsTo<Vehiculo, $this>
     */
    public function destino(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class, 'vehiculo_destino_id')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoMovimientoEquipamiento::class,
            'cantidad' => 'integer',
            'fecha' => 'date:Y-m-d',
            'anulado_at' => 'datetime',
        ];
    }
}
