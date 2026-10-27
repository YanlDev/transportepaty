<?php

namespace App\Models;

use App\Models\Concerns\RegistraHistorial;
use Database\Factories\ViaticoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Un viático depositado al conductor de un ticket: uno de su tarjeta de la
 * pizarra (con lo previsto) o un imprevisto. Se puede corregir y se anula con
 * su motivo; nunca se borra, y cada movimiento queda en el historial.
 *
 * @property int $id
 * @property int $vuelta_id
 * @property int $vehiculo_id
 * @property int|null $conductor_id
 * @property int|null $linea_regla_abastecimiento_id Null si es un imprevisto.
 * @property string $concepto
 * @property string|null $monto_previsto Lo que dice la pizarra.
 * @property string $monto Lo depositado.
 * @property Carbon $fecha
 * @property string|null $numero_operacion
 * @property string|null $observacion
 * @property int|null $user_id
 * @property Carbon|null $anulado_at
 * @property int|null $anulado_por
 * @property string|null $motivo_anulacion
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Vuelta $vuelta
 * @property-read Vehiculo $vehiculo
 * @property-read Conductor|null $conductor
 * @property-read LineaReglaAbastecimiento|null $linea
 * @property-read User|null $registradoPor
 * @property-read User|null $anuladoPor
 */
#[Fillable([
    'vuelta_id', 'vehiculo_id', 'conductor_id', 'linea_regla_abastecimiento_id', 'concepto',
    'monto_previsto', 'monto', 'fecha', 'numero_operacion', 'observacion', 'user_id',
    'anulado_at', 'anulado_por', 'motivo_anulacion',
])]
class Viatico extends Model
{
    /** @use HasFactory<ViaticoFactory> */
    use HasFactory, RegistraHistorial;

    /**
     * @param  Builder<self>  $query
     */
    public function scopeVigentes(Builder $query): void
    {
        $query->whereNull('anulado_at');
    }

    public function estaAnulado(): bool
    {
        return $this->anulado_at !== null;
    }

    public function etiquetaHistorial(): ?string
    {
        return $this->concepto.($this->vuelta_id ? ' · '.Vuelta::query()->whereKey($this->vuelta_id)->value('codigo') : '');
    }

    /**
     * @return BelongsTo<Vuelta, $this>
     */
    public function vuelta(): BelongsTo
    {
        return $this->belongsTo(Vuelta::class);
    }

    /**
     * @return BelongsTo<Vehiculo, $this>
     */
    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Conductor, $this>
     */
    public function conductor(): BelongsTo
    {
        return $this->belongsTo(Conductor::class);
    }

    /**
     * @return BelongsTo<LineaReglaAbastecimiento, $this>
     */
    public function linea(): BelongsTo
    {
        return $this->belongsTo(LineaReglaAbastecimiento::class, 'linea_regla_abastecimiento_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function anuladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monto_previsto' => 'decimal:2',
            'monto' => 'decimal:2',
            'fecha' => 'date',
            'anulado_at' => 'datetime',
        ];
    }
}
