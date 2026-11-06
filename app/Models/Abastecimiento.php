<?php

namespace App\Models;

use App\Enums\SentidoTramo;
use App\Models\Concerns\RegistraHistorial;
use Database\Factories\AbastecimientoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Combustible que abastecimiento autorizó para una unidad (por el QR del
 * convenio con Repsol, o en Kio): cuántos galones, en qué punto, para qué
 * ticket y en qué tramo. No se borra: se anula con motivo.
 *
 * @property int $id
 * @property int $vehiculo_id
 * @property int|null $vuelta_id
 * @property SentidoTramo|null $sentido Ida o vuelta: a qué tramo del ticket va.
 * @property int|null $punto_abastecimiento_id
 * @property string|null $punto_libre Un grifo fuera de la lista, escrito a mano.
 * @property int|null $conductor_id
 * @property Carbon $fecha
 * @property string $galones Los autorizados (0 en el tanque full: lo dice el voucher).
 * @property bool $tanque_lleno Se llenó el tanque (Kio): no suma al tope del tramo y se regulariza con el voucher.
 * @property string|null $precio_autorizado Soles por galón con que se autorizó: el costo estimado hasta el voucher.
 * @property string|null $precio_galon Soles por galón en ese grifo, según el voucher.
 * @property string|null $importe Galones despachados × precio, en soles.
 * @property string|null $motivo_exceso Por qué se autorizó más que el tope de la tarjeta.
 * @property string|null $galones_despachados Los que de verdad cargó, según el voucher.
 * @property int|null $kilometraje El odómetro al cargar.
 * @property Carbon|null $despachado_el
 * @property int|null $despacho_por
 * @property string|null $observacion
 * @property int|null $user_id
 * @property Carbon|null $anulado_at
 * @property int|null $anulado_por
 * @property string|null $motivo_anulacion
 * @property-read Vehiculo $vehiculo
 * @property-read Vuelta|null $vuelta
 * @property-read PuntoAbastecimiento|null $punto
 * @property-read Conductor|null $conductor
 * @property-read User|null $registradoPor
 * @property-read User|null $anuladoPor
 * @property-read User|null $despachoRegistradoPor
 */
#[Fillable([
    'vehiculo_id', 'vuelta_id', 'sentido', 'punto_abastecimiento_id', 'punto_libre', 'conductor_id', 'fecha',
    'galones', 'tanque_lleno', 'precio_autorizado', 'precio_galon', 'importe', 'observacion', 'user_id',
    'anulado_at', 'anulado_por', 'motivo_anulacion', 'motivo_exceso',
    'galones_despachados', 'kilometraje', 'despachado_el', 'despacho_por',
])]
class Abastecimiento extends Model implements HasMedia
{
    /**
     * `galonesQueCuentan()` en SQL, para sumar sin traer los registros.
     */
    public const GALONES_QUE_CUENTAN = 'coalesce(galones_despachados, galones)';

    /** @use HasFactory<AbastecimientoFactory> */
    use HasFactory, InteractsWithMedia, RegistraHistorial;

    public function etiquetaHistorial(): ?string
    {
        return Vehiculo::withTrashed()->whereKey($this->vehiculo_id)->value('placa').' · '.$this->galones.' gal';
    }

    /**
     * Los que cuentan: los anulados quedan solo como registro.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVigentes(Builder $query): void
    {
        $query->whereNull($query->qualifyColumn('anulado_at'));
    }

    public function estaAnulado(): bool
    {
        return $this->anulado_at !== null;
    }

    /**
     * Si ya se registró lo que de verdad cargó (el voucher del grifo).
     */
    public function tieneDespacho(): bool
    {
        return $this->galones_despachados !== null;
    }

    /**
     * Lo que cuenta contra el tope del tramo: lo que de verdad cargó según
     * el voucher; mientras no llegue, lo autorizado. Si autorizaron 150 y
     * cargó 100, al tramo le quedan esos 50.
     */
    /**
     * La foto del voucher del grifo: una por combustible, la última vale.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('voucher')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/heic']);
    }

    public function galonesQueCuentan(): float
    {
        return (float) ($this->galones_despachados ?? $this->galones);
    }

    /**
     * Lo que cuesta: el importe del voucher si ya llegó; si no, lo
     * autorizado por su precio. Null si no hay ni lo uno ni lo otro.
     */
    public function costo(): ?float
    {
        if ($this->tieneDespacho() && $this->importe !== null) {
            return (float) $this->importe;
        }

        return $this->precio_autorizado === null ? null : round((float) $this->galones * (float) $this->precio_autorizado, 2);
    }

    /**
     * @return BelongsTo<Vehiculo, $this>
     */
    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Vuelta, $this>
     */
    public function vuelta(): BelongsTo
    {
        return $this->belongsTo(Vuelta::class);
    }

    /**
     * @return BelongsTo<PuntoAbastecimiento, $this>
     */
    public function punto(): BelongsTo
    {
        return $this->belongsTo(PuntoAbastecimiento::class, 'punto_abastecimiento_id');
    }

    /**
     * Dónde cargó: el punto de la lista o, en un caso excepcional, el grifo
     * escrito a mano. Necesita `punto` cargado.
     */
    public function nombrePunto(): ?string
    {
        return $this->punto->nombre ?? (filled($this->punto_libre) ? $this->punto_libre : null);
    }

    /**
     * @return BelongsTo<Conductor, $this>
     */
    public function conductor(): BelongsTo
    {
        return $this->belongsTo(Conductor::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function despachoRegistradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'despacho_por');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function anuladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'sentido' => SentidoTramo::class,
            'galones' => 'decimal:2',
            'tanque_lleno' => 'boolean',
            'anulado_at' => 'datetime',
            'galones_despachados' => 'decimal:2',
            'kilometraje' => 'integer',
            'despachado_el' => 'date',
            'precio_galon' => 'decimal:3',
            'precio_autorizado' => 'decimal:3',
            'importe' => 'decimal:2',
        ];
    }
}
