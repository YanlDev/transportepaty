<?php

namespace App\Models;

use App\Enums\EstadoOrdenTrabajo;
use App\Enums\TipoOrdenTrabajo;
use App\Models\Concerns\RegistraHistorial;
use Database\Factories\OrdenTrabajoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Un trabajo de taller sobre una unidad: el «Cuadro de trabajos de taller»
 * del Excel, con su panel fotográfico de antes y después.
 *
 * Una preventiva finalizada es la que mueve el plan: su kilometraje pasa a
 * ser la base desde la que se cuenta el próximo servicio de su nivel y de
 * los menores. No se escribe en ningún contador: `ProximoMantenimiento` lo
 * deduce de las órdenes cada vez.
 *
 * @property int $id
 * @property int $vehiculo_id
 * @property TipoOrdenTrabajo $tipo
 * @property int|null $servicio_mantenimiento_id
 * @property EstadoOrdenTrabajo $estado
 * @property Carbon $fecha
 * @property int|null $kilometraje
 * @property Carbon|null $inicio
 * @property Carbon|null $termino
 * @property string $trabajo
 * @property string|null $tecnico
 * @property string|null $observacion
 * @property int|null $user_id
 * @property-read Vehiculo $vehiculo
 * @property-read ServicioMantenimiento|null $servicio
 */
#[Fillable([
    'vehiculo_id',
    'tipo',
    'servicio_mantenimiento_id',
    'estado',
    'fecha',
    'kilometraje',
    'inicio',
    'termino',
    'trabajo',
    'tecnico',
    'observacion',
    'user_id',
])]
class OrdenTrabajo extends Model implements HasMedia
{
    /** @use HasFactory<OrdenTrabajoFactory> */
    use HasFactory, RegistraHistorial;

    use InteractsWithMedia;

    protected $table = 'ordenes_trabajo';

    /**
     * @return BelongsTo<Vehiculo, $this>
     */
    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class)->withTrashed();
    }

    /**
     * @return BelongsTo<ServicioMantenimiento, $this>
     */
    public function servicio(): BelongsTo
    {
        return $this->belongsTo(ServicioMantenimiento::class, 'servicio_mantenimiento_id');
    }

    /**
     * El panel fotográfico: cómo llegó la unidad y cómo quedó.
     */
    public function registerMediaCollections(): void
    {
        foreach (['antes', 'despues'] as $coleccion) {
            $this->addMediaCollection($coleccion)
                ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
        }
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoOrdenTrabajo::class,
            'estado' => EstadoOrdenTrabajo::class,
            'fecha' => 'date:Y-m-d',
            'kilometraje' => 'integer',
            'inicio' => 'datetime',
            'termino' => 'datetime',
        ];
    }
}
