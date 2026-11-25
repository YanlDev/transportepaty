<?php

namespace App\Models;

use App\Models\Concerns\RegistraHistorial;
use App\Models\Concerns\SeAnula;
use Database\Factories\ReposicionEquipamientoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Taller pide reponer algo del equipamiento de una unidad: la pala que
 * falta, el botiquín dañado. El almacén lo ve en su «Por hacer» y lo
 * entrega con vale o pide su compra. Queda atendido cuando sale el vale: eso
 * no se guarda, lo dice `ReposicionesDeEquipamiento`.
 *
 * @property int $id
 * @property int $vehiculo_id
 * @property int $item_equipamiento_id
 * @property int $cantidad
 * @property string|null $nota
 * @property int|null $user_id
 * @property Carbon|null $anulado_at
 * @property Carbon $created_at
 * @property-read Vehiculo $vehiculo
 * @property-read ItemEquipamiento $item
 * @property-read User|null $user
 */
#[Fillable(['vehiculo_id', 'item_equipamiento_id', 'cantidad', 'nota', 'user_id', 'anulado_at', 'anulado_por', 'motivo_anulacion'])]
class ReposicionEquipamiento extends Model
{
    /** @use HasFactory<ReposicionEquipamientoFactory> */
    use HasFactory, RegistraHistorial, SeAnula;

    protected $table = 'reposiciones_equipamiento';

    /**
     * @return BelongsTo<Vehiculo, $this>
     */
    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class)->withTrashed();
    }

    /**
     * @return BelongsTo<ItemEquipamiento, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(ItemEquipamiento::class, 'item_equipamiento_id');
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
            'cantidad' => 'integer',
            'anulado_at' => 'datetime',
        ];
    }
}
