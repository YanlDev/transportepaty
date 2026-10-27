<?php

namespace App\Models;

use App\Enums\EstadoVuelta;
use App\Enums\OrigenVuelta;
use App\Enums\SentidoTramo;
use App\Enums\TipoVuelta;
use App\Models\Concerns\RegistraHistorial;
use Carbon\CarbonInterface;
use Database\Factories\VueltaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * El ticket del viaje: una vuelta de la unidad, con su ida (sale de Juliaca
 * o de la mina y baja a la costa) y su regreso (vuelve a subir). Cada tramo
 * tiene una o más paradas, y las GR cuelgan de la parada en la que se hizo
 * la carga.
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
 * @property-read Collection<int, Abastecimiento> $abastecimientos
 * @property-read Collection<int, EnvioWhatsapp> $envios
 * @property-read Collection<int, RevisionTaller> $revisiones
 * @property-read RevisionTaller|null $revision La última: la que vale.
 * @property-read bool|null $abastecimientos_exists Solo si se cargó con `withExists`.
 * @property-read bool|null $viaticos_exists Solo si se cargó con `withExists`.
 */
#[Fillable(['tracto_id', 'carreta_id', 'conductor_id', 'tipo', 'estado', 'origen', 'inicio', 'fin'])]
class Vuelta extends Model
{
    /** @use HasFactory<VueltaFactory> */
    use HasFactory, RegistraHistorial;

    public function etiquetaHistorial(): ?string
    {
        return $this->codigo;
    }

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
     * Los avisos por WhatsApp de la vuelta, del más nuevo al más viejo.
     *
     * @return HasMany<EnvioWhatsapp, $this>
     */
    public function envios(): HasMany
    {
        return $this->hasMany(EnvioWhatsapp::class)->latest('id');
    }

    /**
     * Las revisiones de taller, de la más nueva a la más vieja.
     *
     * @return HasMany<RevisionTaller, $this>
     */
    public function revisiones(): HasMany
    {
        return $this->hasMany(RevisionTaller::class)->latest('id');
    }

    /**
     * La última revisión de taller: la que vale.
     *
     * @return HasOne<RevisionTaller, $this>
     */
    public function revision(): HasOne
    {
        return $this->hasOne(RevisionTaller::class)->latestOfMany();
    }

    /**
     * Si taller ya dio el apto: sin él, el ticket no avanza.
     */
    public function tieneOkDeTaller(): bool
    {
        return $this->revision?->esApta() ?? false;
    }

    /**
     * El combustible autorizado para la vuelta.
     *
     * @return HasMany<Abastecimiento, $this>
     */
    public function abastecimientos(): HasMany
    {
        return $this->hasMany(Abastecimiento::class);
    }

    /**
     * @return HasMany<Viatico, $this>
     */
    public function viaticos(): HasMany
    {
        return $this->hasMany(Viatico::class);
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
     * En qué anda la unidad con este ticket: por salir, en ida hasta que la
     * vuelta (el regreso) tiene GR, en vuelta hasta que esa GR llega a la
     * base (Juliaca o la mina), y en base cuando ya está disponible para el
     * siguiente. Necesita `paradas.viajes` cargadas.
     *
     * @return 'programada'|'en_ida'|'en_vuelta'|'en_base'
     */
    public function etapa(): string
    {
        if ($this->estado === EstadoVuelta::Programada) {
            return 'programada';
        }

        if ($this->estado !== EstadoVuelta::EnCurso) {
            return 'en_base';
        }

        $ultima = $this->paradas->last(fn (ParadaVuelta $parada): bool => $parada->viajes->isNotEmpty());

        if ($ultima?->sentido !== SentidoTramo::Regreso) {
            return 'en_ida';
        }

        return $ultima->zona_destino?->esBase() ? 'en_base' : 'en_vuelta';
    }

    /**
     * Las de una unidad que están programadas o andando y que ya arrancaron
     * (o arrancan) hasta ese día.
     *
     * @param  Builder<self>  $query
     */
    public function scopeAbiertasDeLaUnidad(Builder $query, int $tractoId, CarbonInterface $hasta): void
    {
        $query->where('tracto_id', $tractoId)
            ->whereIn('estado', [EstadoVuelta::Programada, EstadoVuelta::EnCurso])
            ->whereDate('inicio', '<=', $hasta->toDateString());
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
