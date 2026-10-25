<?php

namespace App\Models;

use App\Enums\EstadoVuelta;
use App\Enums\OrigenVuelta;
use App\Enums\SentidoTramo;
use App\Enums\TipoVuelta;
use Database\Factories\VueltaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;

/**
 * El ticket del viaje: una vuelta de la unidad, con su ida (la subida a la
 * sierra) y su regreso (la bajada a la costa). Cada tramo tiene una o más
 * paradas, y las GR cuelgan de la parada en la que se hizo la carga.
 *
 * El código lo recibe al crearse (V-00001) y es la referencia que se le da
 * a las áreas. Las vueltas del historial se rearman desde las GR y su código
 * puede cambiar al rehacerlas; las programadas lo conservan.
 *
 * @property int $id
 * @property string|null $codigo
 * @property int $tracto_id
 * @property int|null $carreta_id
 * @property int|null $conductor_id
 * @property TipoVuelta $tipo
 * @property EstadoVuelta $estado
 * @property OrigenVuelta $origen
 * @property Carbon|null $inicio
 * @property Carbon|null $fin
 * @property-read Vehiculo $tracto
 * @property-read Vehiculo|null $carreta
 * @property-read Conductor|null $conductor
 * @property-read Collection<int, ParadaVuelta> $paradas
 * @property-read Collection<int, Viaje> $viajes
 */
#[Fillable(['tracto_id', 'carreta_id', 'conductor_id', 'tipo', 'estado', 'origen', 'inicio', 'fin'])]
class Vuelta extends Model
{
    /** @use HasFactory<VueltaFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::created(function (Vuelta $vuelta): void {
            $vuelta->codigo = 'V-'.str_pad((string) $vuelta->id, 5, '0', STR_PAD_LEFT);
            $vuelta->saveQuietly();
        });
    }

    /**
     * @return BelongsTo<Vehiculo, $this>
     */
    public function tracto(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class, 'tracto_id')->withTrashed();
    }

    /**
     * @return BelongsTo<Vehiculo, $this>
     */
    public function carreta(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class, 'carreta_id')->withTrashed();
    }

    /**
     * @return BelongsTo<Conductor, $this>
     */
    public function conductor(): BelongsTo
    {
        return $this->belongsTo(Conductor::class);
    }

    /**
     * @return HasMany<ParadaVuelta, $this>
     */
    public function paradas(): HasMany
    {
        return $this->hasMany(ParadaVuelta::class)->orderBy('orden');
    }

    /**
     * @return HasManyThrough<Viaje, ParadaVuelta, $this>
     */
    public function viajes(): HasManyThrough
    {
        return $this->hasManyThrough(Viaje::class, ParadaVuelta::class, 'vuelta_id', 'parada_vuelta_id');
    }

    /**
     * Las paradas de un tramo, en orden.
     *
     * @return Collection<int, ParadaVuelta>
     */
    public function paradasDe(SentidoTramo $sentido): Collection
    {
        return $this->paradas->where('sentido', $sentido)->values();
    }

    /**
     * Las que se armaron solas desde las GR.
     *
     * @param  Builder<self>  $query
     */
    public function scopeDelHistorial(Builder $query): void
    {
        $query->where('origen', OrigenVuelta::Historial);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoVuelta::class,
            'estado' => EstadoVuelta::class,
            'origen' => OrigenVuelta::class,
            'inicio' => 'date',
            'fin' => 'date',
        ];
    }
}
