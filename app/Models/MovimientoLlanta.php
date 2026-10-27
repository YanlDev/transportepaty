<?php

namespace App\Models;

use App\Enums\TipoMovimientoLlanta;
use App\Models\Concerns\RegistraHistorial;
use Database\Factories\MovimientoLlantaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Algo que se le hizo a una llanta: el historial de la posición y de la
 * llanta. La profundidad se anota al instalarla, al medirla y al retirarla.
 *
 * @property int $id
 * @property int $llanta_id
 * @property int $vehiculo_id
 * @property TipoMovimientoLlanta $tipo
 * @property Carbon $fecha
 * @property string $posicion
 * @property string|null $posicion_destino
 * @property int|null $kilometraje
 * @property string|null $profundidad_mm
 * @property string|null $observacion
 * @property int|null $user_id
 * @property-read Llanta $llanta
 * @property-read User|null $user
 */
#[Fillable([
    'llanta_id', 'vehiculo_id', 'tipo', 'fecha', 'posicion', 'posicion_destino',
    'kilometraje', 'profundidad_mm', 'observacion', 'user_id',
])]
class MovimientoLlanta extends Model
{
    /** @use HasFactory<MovimientoLlantaFactory> */
    use HasFactory, RegistraHistorial;

    protected $table = 'movimientos_llanta';

    /**
     * @return BelongsTo<Llanta, $this>
     */
    public function llanta(): BelongsTo
    {
        return $this->belongsTo(Llanta::class);
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
            'tipo' => TipoMovimientoLlanta::class,
            'fecha' => 'date',
            'kilometraje' => 'integer',
            'profundidad_mm' => 'decimal:1',
        ];
    }
}
