<?php

namespace App\Models;

use Database\Factories\ProgramacionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Una unidad programada con carga particular para un día.
 *
 * Se carga antes de que exista la guía de remisión —el viaje se registra
 * después, cuando la GR llega— así que esta tabla y `viajes` no se cruzan:
 * esto es lo que se planificó, aquello lo que se despachó. Que una
 * programación no termine en viaje es un hecho normal del negocio, no un
 * dato inconsistente.
 *
 * Cinco campos y nada más, por pedido expreso: la carga tiene que ser rápida.
 *
 * @property int $id
 * @property Carbon $fecha
 * @property int $vehiculo_id
 * @property int $conductor_id
 * @property int $cliente_id
 * @property string $destino
 * @property-read Vehiculo $vehiculo
 * @property-read Conductor $conductor
 * @property-read Cliente $cliente
 */
#[Fillable([
    'fecha',
    'vehiculo_id',
    'conductor_id',
    'cliente_id',
    'destino',
])]
class Programacion extends Model
{
    /** @use HasFactory<ProgramacionFactory> */
    use HasFactory;

    protected $table = 'programaciones';

    /**
     * @return BelongsTo<Vehiculo, $this>
     */
    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class);
    }

    /**
     * @return BelongsTo<Conductor, $this>
     */
    public function conductor(): BelongsTo
    {
        return $this->belongsTo(Conductor::class);
    }

    /**
     * @return BelongsTo<Cliente, $this>
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeDelDia(Builder $query, string $fecha): void
    {
        $query->where('fecha', $fecha);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha' => 'date:Y-m-d',
        ];
    }
}
