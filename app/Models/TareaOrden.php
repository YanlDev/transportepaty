<?php

namespace App\Models;

use Database\Factories\TareaOrdenFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Una cosa por hacer dentro de una orden de trabajo: el mecánico la marca
 * cuando la hizo, y queda quién y cuándo.
 *
 * @property int $id
 * @property int $orden_trabajo_id
 * @property string $descripcion
 * @property int $posicion
 * @property Carbon|null $hecha_at
 * @property int|null $hecha_por
 * @property-read OrdenTrabajo $orden
 * @property-read User|null $hechaPor
 */
#[Fillable(['orden_trabajo_id', 'descripcion', 'posicion', 'hecha_at', 'hecha_por'])]
class TareaOrden extends Model
{
    /** @use HasFactory<TareaOrdenFactory> */
    use HasFactory;

    protected $table = 'tareas_orden';

    /**
     * @return BelongsTo<OrdenTrabajo, $this>
     */
    public function orden(): BelongsTo
    {
        return $this->belongsTo(OrdenTrabajo::class, 'orden_trabajo_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function hechaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hecha_por');
    }

    public function estaHecha(): bool
    {
        return $this->hecha_at !== null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'posicion' => 'integer',
            'hecha_at' => 'datetime',
        ];
    }
}
