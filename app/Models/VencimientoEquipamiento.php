<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Hasta cuándo vale un ítem con vencimiento en una unidad: el extintor, el
 * botiquín. Uno por unidad e ítem; al cambiarlo se pone la fecha nueva.
 *
 * @property int $id
 * @property int $vehiculo_id
 * @property int $item_equipamiento_id
 * @property Carbon $vence_el
 */
#[Fillable(['vehiculo_id', 'item_equipamiento_id', 'vence_el', 'user_id'])]
class VencimientoEquipamiento extends Model
{
    protected $table = 'vencimientos_equipamiento';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'vence_el' => 'date:Y-m-d',
        ];
    }
}
