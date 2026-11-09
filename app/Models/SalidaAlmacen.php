<?php

namespace App\Models;

use App\Enums\DestinoSalida;
use App\Models\Concerns\RegistraHistorial;
use App\Models\Concerns\SeAnula;
use Database\Factories\SalidaAlmacenFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * El vale de salida: lo que se le entrega al taller, siempre con destino.
 * Cada línea sale al costo promedio del momento, así que el vale ya dice
 * cuánto le costó a la unidad o a la orden.
 *
 * @property int $id
 * @property Carbon $fecha
 * @property DestinoSalida $destino
 * @property int|null $vehiculo_id
 * @property int|null $orden_trabajo_id
 * @property string|null $entregado_a
 * @property string|null $observacion
 * @property string $costo_total
 * @property int|null $user_id
 * @property Carbon|null $anulado_at
 * @property int|null $anulado_por
 * @property string|null $motivo_anulacion
 * @property-read Vehiculo|null $vehiculo
 * @property-read OrdenTrabajo|null $ordenTrabajo
 */
#[Fillable([
    'fecha',
    'destino',
    'vehiculo_id',
    'orden_trabajo_id',
    'entregado_a',
    'observacion',
    'costo_total',
    'user_id',
    'anulado_at',
    'anulado_por',
    'motivo_anulacion',
])]
class SalidaAlmacen extends Model
{
    /** @use HasFactory<SalidaAlmacenFactory> */
    use HasFactory, RegistraHistorial, SeAnula;

    protected $table = 'salidas_almacen';

    /**
     * «VS-000012»: el número del vale.
     */
    public function numero(): string
    {
        return sprintf('VS-%06d', $this->id);
    }

    /**
     * @return HasMany<DetalleSalida, $this>
     */
    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleSalida::class);
    }

    /**
     * Las devoluciones de este vale.
     *
     * @return HasMany<IngresoAlmacen, $this>
     */
    public function devoluciones(): HasMany
    {
        return $this->hasMany(IngresoAlmacen::class);
    }

    /**
     * @return BelongsTo<Vehiculo, $this>
     */
    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class)->withTrashed();
    }

    /**
     * @return BelongsTo<OrdenTrabajo, $this>
     */
    public function ordenTrabajo(): BelongsTo
    {
        return $this->belongsTo(OrdenTrabajo::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function etiquetaHistorial(): ?string
    {
        return $this->numero();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha' => 'date:Y-m-d',
            'destino' => DestinoSalida::class,
            'costo_total' => 'decimal:2',
            'anulado_at' => 'datetime',
        ];
    }
}
