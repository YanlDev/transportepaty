<?php

namespace App\Models;

use App\Enums\EstadoVehiculo;
use App\Enums\TipoCombustible;
use App\Enums\TipoVehiculo;
use Carbon\CarbonImmutable;
use Database\Factories\VehiculoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $sucursal_id
 * @property int|null $conductor_id
 * @property string $placa
 * @property string $marca
 * @property string $modelo
 * @property int $anio
 * @property string|null $color
 * @property string|null $numero_serie
 * @property string|null $numero_motor
 * @property string|null $imei
 * @property int|null $gps_km_base
 * @property CarbonImmutable|null $km_calibrado_en
 * @property CarbonImmutable|null $odometro_sincronizado_en
 * @property TipoVehiculo $tipo
 * @property TipoCombustible $combustible
 * @property EstadoVehiculo $estado
 * @property int $kilometraje
 * @property Carbon|null $fecha_adquisicion
 * @property string|null $observaciones
 */
#[Fillable([
    'sucursal_id',
    'conductor_id',
    'placa',
    'marca',
    'modelo',
    'anio',
    'color',
    'numero_serie',
    'numero_motor',
    'imei',
    'gps_km_base',
    'km_calibrado_en',
    'odometro_sincronizado_en',
    'tipo',
    'combustible',
    'estado',
    'kilometraje',
    'fecha_adquisicion',
    'observaciones',
])]
class Vehiculo extends Model
{
    /** @use HasFactory<VehiculoFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'tipo' => TipoVehiculo::Camioneta->value,
        'combustible' => TipoCombustible::Diesel->value,
        'estado' => EstadoVehiculo::Activo->value,
        'kilometraje' => 0,
    ];

    /**
     * @return BelongsTo<Sucursal, $this>
     */
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    /**
     * @return BelongsTo<Conductor, $this>
     */
    public function conductor(): BelongsTo
    {
        return $this->belongsTo(Conductor::class);
    }

    /**
     * @return HasMany<VehiculoFoto, $this>
     */
    public function fotos(): HasMany
    {
        return $this->hasMany(VehiculoFoto::class)->orderBy('orden');
    }

    /**
     * Foto de portada (la de menor orden), para miniaturas en listados.
     *
     * @return HasOne<VehiculoFoto, $this>
     */
    public function fotoPrincipal(): HasOne
    {
        return $this->hasOne(VehiculoFoto::class)->orderBy('orden');
    }

    /**
     * @return HasMany<VehiculoDocumento, $this>
     */
    public function documentos(): HasMany
    {
        return $this->hasMany(VehiculoDocumento::class)->latest();
    }

    /**
     * @return HasMany<Mantenimiento, $this>
     */
    public function mantenimientos(): HasMany
    {
        return $this->hasMany(Mantenimiento::class);
    }

    /**
     * @return HasMany<CargaCombustible, $this>
     */
    public function cargasCombustible(): HasMany
    {
        return $this->hasMany(CargaCombustible::class);
    }

    /**
     * Whether the vehicle is linked to a Tracksolid / GPS device.
     */
    public function tieneGps(): bool
    {
        return $this->imei !== null && $this->imei !== '';
    }

    /**
     * Calibra el odómetro real al valor del tablero y fija el punto de partida
     * desde el cual las sincronizaciones contarán la distancia recorrida.
     */
    public function calibrarOdometro(int $kilometrajeReal): void
    {
        $this->kilometraje = $kilometrajeReal;
        $this->km_calibrado_en = now();
        $this->odometro_sincronizado_en = now();
        $this->save();
    }

    /**
     * Suma al odómetro la distancia recorrida (km del track GPS) desde la
     * última sincronización y avanza el punto de partida. Devuelve los km
     * sumados (0 si no hubo recorrido nuevo).
     */
    public function avanzarOdometroPorRecorrido(float $distanciaKm, CarbonImmutable $hasta): int
    {
        $avance = max(0, (int) round($distanciaKm));

        if ($avance > 0) {
            $this->kilometraje += $avance;
        }

        $this->odometro_sincronizado_en = $hasta;
        $this->save();

        return $avance;
    }

    /**
     * Actualiza el kilometraje a partir del odómetro de una carga de combustible.
     *
     * Sólo aplica a unidades SIN GPS: ahí el odómetro de las cargas es la única
     * fuente de kilometraje y se adopta cuando supera el valor actual. En las
     * unidades con GPS el kilometraje lo lleva la sincronización, así que la
     * carga no lo toca para no romper la contabilidad de `gps_km_base`.
     */
    public function actualizarKilometrajePorCarga(?int $odometro): void
    {
        if ($odometro === null || $this->tieneGps()) {
            return;
        }

        if ($odometro > $this->kilometraje) {
            $this->kilometraje = $odometro;
            $this->save();
        }
    }

    /**
     * Scope the query to vehicles the given user is allowed to see.
     *
     * Admins and viewers see every vehicle; drivers only see the ones
     * assigned to them.
     *
     * @param  Builder<Vehiculo>  $query
     */
    public function scopeVisibleParaUsuario(Builder $query, User $user): void
    {
        if ($user->hasAnyRole(['admin', 'visor'])) {
            return;
        }

        $query->whereHas('conductor', fn (Builder $query) => $query->where('user_id', $user->id));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoVehiculo::class,
            'combustible' => TipoCombustible::class,
            'estado' => EstadoVehiculo::class,
            'anio' => 'integer',
            'kilometraje' => 'integer',
            'gps_km_base' => 'integer',
            'km_calibrado_en' => 'datetime',
            'odometro_sincronizado_en' => 'datetime',
            'fecha_adquisicion' => 'date:Y-m-d',
        ];
    }
}
