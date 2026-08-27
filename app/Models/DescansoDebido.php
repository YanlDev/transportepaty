<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Cuántos días de descanso se le deben a un conductor en un mes puntual —un
 * número que el admin escribe a mano, no algo que la app calcule: la deuda
 * incluye meses de antes de que este sistema existiera, así que no hay
 * marcas de asistencia con las que reconstruirla.
 *
 * @property int $id
 * @property int $conductor_id
 * @property Carbon $mes
 * @property int $dias_debidos
 * @property string|null $notas
 * @property-read Conductor $conductor
 */
#[Fillable([
    'conductor_id',
    'mes',
    'dias_debidos',
    'notas',
])]
class DescansoDebido extends Model
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
            'mes' => 'date:Y-m-d',
            'dias_debidos' => 'integer',
        ];
    }
}
