<?php

namespace App\Models;

use Database\Factories\AsignacionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Vínculo entre un conductor y los fierros que maneja: el tracto y, si la lleva,
 * la carreta. Una asignación con `hasta` nulo es la vigente; al reasignar se
 * cierra la anterior con fecha en vez de sobrescribirla, de modo que el
 * historial de quién anduvo con qué unidad queda disponible.
 *
 * @property int $id
 * @property int $conductor_id
 * @property int $tracto_id
 * @property int|null $carreta_id
 * @property Carbon $desde
 * @property Carbon|null $hasta
 * @property string|null $observaciones
 * @property-read Conductor $conductor
 * @property-read Vehiculo $tracto
 * @property-read Vehiculo|null $carreta
 */
#[Fillable([
    'conductor_id',
    'tracto_id',
    'carreta_id',
    'desde',
    'hasta',
    'observaciones',
])]
class Asignacion extends Model
{
    /** @use HasFactory<AsignacionFactory> */
    use HasFactory;

    protected $table = 'asignaciones';

    /**
     * @return BelongsTo<Conductor, $this>
     */
    public function conductor(): BelongsTo
    {
        return $this->belongsTo(Conductor::class);
    }

    /**
     * Incluye tractos dados de baja (soft delete): el historial debe seguir
     * mostrando con qué fierro se trabajó aunque este ya no exista en la flota.
     *
     * @return BelongsTo<Vehiculo, $this>
     */
    public function tracto(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class, 'tracto_id')->withTrashed();
    }

    /**
     * @return BelongsTo<Vehiculo, $this>
     */
    public function carreta(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class, 'carreta_id')->withTrashed();
    }

    /**
     * Asignaciones que siguen en curso.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeVigentes(Builder $query): void
    {
        $query->whereNull('hasta');
    }

    /**
     * Asignaciones ya cerradas.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeFinalizadas(Builder $query): void
    {
        $query->whereNotNull('hasta');
    }

    public function estaVigente(): bool
    {
        return $this->hasta === null;
    }

    /**
     * Cierra la asignación y libera al conductor, el tracto y la carreta para
     * que puedan entrar en una nueva.
     */
    public function liberar(): bool
    {
        return $this->fill(['hasta' => now()->toDateString()])->save();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'desde' => 'date:Y-m-d',
            'hasta' => 'date:Y-m-d',
        ];
    }
}
