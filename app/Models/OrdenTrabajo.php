<?php

namespace App\Models;

use App\Enums\EstadoOrdenTrabajo;
use App\Enums\PrioridadOrden;
use App\Enums\TipoOrdenTrabajo;
use App\Models\Concerns\RegistraHistorial;
use App\Models\Concerns\SeAnula;
use Database\Factories\OrdenTrabajoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
 * Tampoco guarda materiales: lo que consumió son las líneas de los vales de
 * salida del almacén que la nombran (ver `MaterialesDeOrden`). Y no se borra:
 * se anula, y una anulada no cuenta para el plan.
 *
 * La ejecutan uno o varios mecánicos, cada uno con su usuario: la orden
 * recuerda quién la empezó, quién la terminó y quién de taller la revisó
 * (ver `EjecucionDeOrden`).
 *
 * @property int $id
 * @property int $vehiculo_id
 * @property TipoOrdenTrabajo $tipo
 * @property int|null $servicio_mantenimiento_id
 * @property EstadoOrdenTrabajo $estado
 * @property PrioridadOrden $prioridad
 * @property Carbon|null $requerimiento_at
 * @property int|null $requerimiento_por
 * @property int|null $requerimiento_registrado_por
 * @property Carbon|null $aprobado_at
 * @property int|null $aprobado_por
 * @property string|null $devolucion_requerimiento
 * @property Carbon $fecha
 * @property int|null $kilometraje
 * @property Carbon|null $inicio
 * @property int|null $iniciado_por
 * @property int|null $inicio_registrado_por
 * @property Carbon|null $termino
 * @property int|null $terminado_por
 * @property int|null $termino_registrado_por
 * @property string|null $nota_mecanico
 * @property int|null $revisado_por
 * @property Carbon|null $revisado_at
 * @property string|null $observacion_revision
 * @property string $trabajo
 * @property string|null $tecnico
 * @property string|null $observacion
 * @property string|null $justificacion_materiales
 * @property int|null $user_id
 * @property Carbon|null $anulado_at
 * @property int|null $anulado_por
 * @property string|null $motivo_anulacion
 * @property-read Vehiculo $vehiculo
 * @property-read ServicioMantenimiento|null $servicio
 */
#[Fillable([
    'vehiculo_id',
    'tipo',
    'servicio_mantenimiento_id',
    'estado',
    'prioridad',
    'requerimiento_at',
    'requerimiento_por',
    'requerimiento_registrado_por',
    'aprobado_at',
    'aprobado_por',
    'devolucion_requerimiento',
    'fecha',
    'kilometraje',
    'inicio',
    'iniciado_por',
    'inicio_registrado_por',
    'termino',
    'terminado_por',
    'termino_registrado_por',
    'nota_mecanico',
    'revisado_por',
    'revisado_at',
    'observacion_revision',
    'trabajo',
    'tecnico',
    'observacion',
    'justificacion_materiales',
    'user_id',
    'anulado_at',
    'anulado_por',
    'motivo_anulacion',
])]
class OrdenTrabajo extends Model implements HasMedia
{
    /** @use HasFactory<OrdenTrabajoFactory> */
    use HasFactory, RegistraHistorial, SeAnula;

    use InteractsWithMedia;

    protected $table = 'ordenes_trabajo';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'prioridad' => 'normal',
    ];

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
     * Las entregas del almacén que nombran esta orden.
     *
     * @return HasMany<SalidaAlmacen, $this>
     */
    public function salidas(): HasMany
    {
        return $this->hasMany(SalidaAlmacen::class);
    }

    /**
     * Los mecánicos que la ejecutan; uno de ellos es el responsable.
     *
     * @return BelongsToMany<User, $this>
     */
    public function mecanicos(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'orden_trabajo_mecanico')
            ->withPivot('responsable')
            ->withTimestamps()
            ->orderByDesc('orden_trabajo_mecanico.responsable')
            ->orderBy('users.name');
    }

    /**
     * @return HasMany<TareaOrden, $this>
     */
    public function tareas(): HasMany
    {
        return $this->hasMany(TareaOrden::class)->orderBy('posicion')->orderBy('id');
    }

    /**
     * Los repuestos que el mecánico pidió para la orden.
     *
     * @return HasMany<RepuestoOrden, $this>
     */
    public function repuestos(): HasMany
    {
        return $this->hasMany(RepuestoOrden::class)->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requerimientoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requerimiento_por');
    }

    /**
     * Quien aprobó el requerimiento del mecánico.
     *
     * @return BelongsTo<User, $this>
     */
    public function aprobadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }

    /**
     * Quien la registró o la pidió.
     *
     * @return BelongsTo<User, $this>
     */
    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function iniciadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'iniciado_por');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function terminadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'terminado_por');
    }

    /**
     * Quien anotó el requerimiento por el mecánico, cuando no lo anotó él.
     *
     * @return BelongsTo<User, $this>
     */
    public function requerimientoRegistradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requerimiento_registrado_por');
    }

    /**
     * Quien anotó el inicio por el mecánico, cuando no lo anotó él.
     *
     * @return BelongsTo<User, $this>
     */
    public function inicioRegistradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inicio_registrado_por');
    }

    /**
     * Quien anotó el término por el mecánico, cuando no lo anotó él.
     *
     * @return BelongsTo<User, $this>
     */
    public function terminoRegistradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'termino_registrado_por');
    }

    /**
     * Quien de taller la aprobó o la observó.
     *
     * @return BelongsTo<User, $this>
     */
    public function revisadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por');
    }

    /**
     * Las que tienen a la unidad en taller: vigentes y sin finalizar, desde
     * la pre-orden.
     *
     * @param  Builder<static>  $query
     */
    public function scopeEnTaller(Builder $query): void
    {
        $query->vigentes()->whereIn($this->qualifyColumn('estado'), EstadoOrdenTrabajo::enTaller());
    }

    /**
     * Las asignadas a un mecánico.
     *
     * @param  Builder<static>  $query
     */
    public function scopeDeMecanico(Builder $query, User $mecanico): void
    {
        $query->whereHas('mecanicos', fn (Builder $mecanicos) => $mecanicos->whereKey($mecanico->id));
    }

    public function esDeMecanico(User $mecanico): bool
    {
        return $this->mecanicos()->whereKey($mecanico->id)->exists();
    }

    public function numero(): string
    {
        return sprintf('OT-%05d', $this->id);
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
            'prioridad' => PrioridadOrden::class,
            'revisado_at' => 'datetime',
            'requerimiento_at' => 'datetime',
            'aprobado_at' => 'datetime',
            'fecha' => 'date:Y-m-d',
            'kilometraje' => 'integer',
            'inicio' => 'datetime',
            'termino' => 'datetime',
            'anulado_at' => 'datetime',
        ];
    }
}
