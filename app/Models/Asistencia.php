<?php

namespace App\Models;

use App\Enums\EstadoAsistencia;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * El estado de un conductor en un día puntual: trabajó, faltó, está de
 * vacaciones o de descanso. Reemplaza el rooster en papel/Excel — una fila
 * por conductor y columna por día no es necesaria acá porque cada celda de
 * ese rooster es, en realidad, una fila de esta tabla.
 *
 * @property int $id
 * @property int $conductor_id
 * @property Carbon $fecha
 * @property EstadoAsistencia $estado
 * @property string|null $observaciones
 * @property-read Conductor $conductor
 */
#[Fillable([
    'conductor_id',
    'fecha',
    'estado',
    'observaciones',
])]
class Asistencia extends Model
{
    /**
     * @return BelongsTo<Conductor, $this>
     */
    public function conductor(): BelongsTo
    {
        return $this->belongsTo(Conductor::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha' => 'date:Y-m-d',
            'estado' => EstadoAsistencia::class,
        ];
    }
}
