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
     * Calibra el odómetro real: fija el kilometraje a la lectura real del
     * tablero y recuerda cuánto marcaba el GPS en ese momento, para que las
     * sincronizaciones siguientes sólo sumen lo recorrido.
     */
    public function calibrarOdometro(int $kilometrajeReal, ?int $lecturaGps): void
    {
        $this->kilometraje = $kilometrajeReal;
        $this->gps_km_base = $lecturaGps;
        $this->km_calibrado_en = now();
        $this->save();
    }

    /**
     * Aplica una lectura del odómetro del GPS al kilometraje real, sumando
     * sólo el avance desde la última lectura. La primera vez fija la base sin
     * mover el odómetro; si el equipo se reinicia (la lectura baja), no resta.
     *
     * @return bool true si el kilometraje real cambió.
     */
    public function aplicarLecturaGps(int $lecturaGps): bool
    {
        if ($this->gps_km_base === null) {
            $this->gps_km_base = $lecturaGps;
            $this->save();

            return false;
        }

        $avance = $lecturaGps - $this->gps_km_base;

        if ($avance <= 0) {
            return false;
        }

        $this->kilometraje += $avance;
        $this->gps_km_base = $lecturaGps;
        $this->save();

        return true;
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
            'fecha_adquisicion' => 'date:Y-m-d',
        ];
    }
}
