<?php

namespace App\Models;

use App\Enums\TipoNovedad;
use Database\Factories\NovedadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Un hecho de campo que saca a una unidad de la programación: no habida,
 * infracción en mina, ya está arriba, subió como adicional, o entró a taller.
 *
 * No se deduce de ningún dato: alguien lo ve y lo registra. Es lo que cierra el
 * bucle entre lo que pasa en ruta y quién puede subir mañana.
 *
 * @property int $id
 * @property int $tracto_id
 * @property TipoNovedad $tipo
 * @property Carbon $desde
 * @property Carbon|null $hasta
 * @property string|null $motivo
 * @property-read Vehiculo $tracto
 */
#[Fillable([
    'tracto_id',
    'tipo',
    'desde',
    'hasta',
    'motivo',
])]
class Novedad extends Model
{
    /** @use HasFactory<NovedadFactory> */
    use HasFactory;

    protected $table = 'novedades';

    /**
     * @return BelongsTo<Vehiculo, $this>
     */
    public function tracto(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class, 'tracto_id')->withTrashed();
    }

    public function estaVigente(): bool
    {
        return $this->hasta === null;
    }

    /**
     * El motivo escrito a mano si lo hay, y si no el del tipo. Es lo que se lee
     * en la columna de no programables.
     */
    public function motivoLegible(): string
    {
        return $this->motivo ?: $this->tipo->motivo();
    }

    /**
     * Cierra la novedad, devolviendo la unidad a la programación.
     */
    public function levantar(string $fecha): bool
    {
        return $this->fill(['hasta' => $fecha])->save();
    }

    /**
     * Novedades que pesaban en la fecha indicada: ya habían empezado y no se
     * habían levantado todavía. Se consulta por fecha y no por «hoy» para poder
     * rehacer la programación de un día pasado tal como se decidió entonces.
     *
     * `hasta` es el día en que se levantó, y desde ese mismo día la novedad ya
     * no pesa: si la unidad apareció esta mañana, tiene que poder programarse
     * hoy, no mañana.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeVigentesEn(Builder $query, string $fecha): void
    {
        $query->where('desde', '<=', $fecha)
            ->where(fn (Builder $abierta) => $abierta
                ->whereNull('hasta')
                ->orWhere('hasta', '>', $fecha));
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeVigentes(Builder $query): void
    {
        $query->whereNull('hasta');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoNovedad::class,
            'desde' => 'date:Y-m-d',
            'hasta' => 'date:Y-m-d',
        ];
    }
}
