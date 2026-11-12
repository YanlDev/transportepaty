<?php

namespace App\Models;

use App\Enums\EstadoVuelta;
use App\Enums\OrigenVuelta;
use App\Enums\SentidoTramo;
use App\Enums\TipoVuelta;
use App\Enums\ZonaRuta;
use App\Models\Concerns\RegistraHistorial;
use Carbon\CarbonImmutable;
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
 * El ticket del viaje: una vuelta de la unidad, de Juliaca a Juliaca, con
 * su ida (sale de Juliaca, sube a la mina si va por concentrado y baja a la
 * costa) y su regreso (vuelve a subir hasta Juliaca). En Juliaca se reinicia
 * el combustible: por eso ahí termina un ticket y empieza el siguiente. Cada tramo
 * tiene una o más paradas, y las GR cuelgan de la parada en la que se hizo
 * la carga.
 *
 * El código lo recibe al crearse (V-00001) y es la referencia que se le da
 * a las áreas. Cada paso lo da una persona: se programa, taller da el OK,
 * las GR se asignan a mano, se registra la llegada a la base y se cierra.
 * Nada cambia de estado porque se dedujo.
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
 * @property Carbon|null $llegada_el El día en que alguien registró que llegó a la base.
 * @property int|null $km_llegada
 * @property int|null $llegada_por
 * @property-read Vehiculo $tracto
 * @property-read Vehiculo|null $carreta
 * @property-read Conductor|null $conductor
 * @property-read Collection<int, ParadaVuelta> $paradas
 * @property-read Collection<int, Viaje> $viajes
 * @property-read Collection<int, Abastecimiento> $abastecimientos
 * @property-read Collection<int, EnvioWhatsapp> $envios
 * @property-read Collection<int, RevisionTaller> $revisiones
 * @property-read RevisionTaller|null $revision La última: la que vale.
 * @property-read Collection<int, RutaVuelta> $rutas Las rutas de cada tramo, copiadas al elegirlas.
 * @property-read User|null $llegadaRegistradaPor
 * @property-read bool|null $abastecimientos_exists Solo si se cargó con `withExists`.
 * @property-read bool|null $viaticos_exists Solo si se cargó con `withExists`.
 */
#[Fillable(['tracto_id', 'carreta_id', 'conductor_id', 'tipo', 'estado', 'origen', 'inicio', 'fin', 'llegada_el', 'km_llegada', 'llegada_por'])]
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
     * Las rutas elegidas para cada tramo: la principal primero y después sus
     * complementos.
     *
     * @return HasMany<RutaVuelta, $this>
     */
    public function rutas(): HasMany
    {
        return $this->hasMany(RutaVuelta::class)->orderBy('orden')->orderBy('id');
    }

    /**
     * @return Collection<int, RutaVuelta>
     */
    public function rutasDe(SentidoTramo $sentido): Collection
    {
        // Las rutas por tramos leen las paradas de este mismo ticket.
        return $this->rutas->where('sentido', $sentido)->values()
            ->each(fn (RutaVuelta $ruta) => $ruta->setRelation('vuelta', $this));
    }

    /**
     * El tope de combustible del tramo: la suma de sus rutas. Null si no
     * tiene ruta, si falta elegir una variante o hay galones por definir.
     */
    public function topeDe(SentidoTramo $sentido): ?float
    {
        $rutas = $this->rutasDe($sentido);

        if ($rutas->isEmpty()) {
            return null;
        }

        $topes = $rutas->map(fn (RutaVuelta $ruta): ?float => $ruta->tope());

        return $topes->containsStrict(null) ? null : (float) $topes->sum();
    }

    /**
     * Si al tramo ya se le autorizó combustible o se le depositó un viático
     * de sus rutas (sin contar lo anulado): desde ahí sus rutas quedan
     * congeladas, porque lo autorizado se midió contra ellas.
     */
    public function tramoConGastos(SentidoTramo $sentido): bool
    {
        if ($this->abastecimientos()->vigentes()->where('sentido', $sentido)->exists()) {
            return true;
        }

        $rutas = $this->rutas()->where('sentido', $sentido)->pluck('ruta_id');

        return $this->viaticos()
            ->whereNull('anulado_at')
            ->whereHas('linea', fn (Builder $query) => $query->whereIn('ruta_id', $rutas))
            ->exists();
    }

    /**
     * Si el ticket ya pasó por taller o tiene gastos (aunque estén anulados):
     * el OK de taller y lo gastado son de esa unidad, así que ya no se le
     * cambia la placa.
     */
    public function tieneRevisionOGastos(): bool
    {
        return $this->revisiones()->exists()
            || $this->abastecimientos()->exists()
            || $this->viaticos()->exists();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function llegadaRegistradaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'llegada_por');
    }

    public function estaAbierta(): bool
    {
        return in_array($this->estado, [EstadoVuelta::Programada, EstadoVuelta::EnCurso], true);
    }

    public function llegoABase(): bool
    {
        return $this->llegada_el !== null;
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
     * Por qué al ticket no se le puede registrar combustible ni viáticos, o
     * null si se puede. Es la regla de todo el circuito, en un solo lugar:
     * tiene que estar abierto (sin candado) y con el OK de taller.
     */
    public function bloqueoDeGastos(): ?string
    {
        if (! in_array($this->estado, [EstadoVuelta::Programada, EstadoVuelta::EnCurso], true)) {
            return "El ticket {$this->codigo} está cerrado: hay que reabrirlo para registrarle gastos.";
        }

        if (! $this->tieneOkDeTaller()) {
            return "El ticket {$this->codigo} todavía no tiene el OK de taller.";
        }

        return null;
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
     * vuelta (el regreso) tiene GR, en vuelta hasta que alguien registra que
     * llegó a la base, y en base desde entonces (aunque el ticket siga
     * abierto, esperando su liquidación). No se estima: la llegada la marca
     * una persona. Necesita `paradas.viajes` cargadas.
     *
     * @return 'programada'|'en_ida'|'en_vuelta'|'en_base'
     */
    public function etapa(): string
    {
        if ($this->estado === EstadoVuelta::Programada) {
            return 'programada';
        }

        if ($this->estado !== EstadoVuelta::EnCurso || $this->llegoABase()) {
            return 'en_base';
        }

        $ultima = $this->paradas->last(fn (ParadaVuelta $parada): bool => $parada->viajes->isNotEmpty());

        return $ultima?->sentido === SentidoTramo::Regreso ? 'en_vuelta' : 'en_ida';
    }

    /**
     * Cuándo debería llegar a la base, como referencia para avisar de un
     * atraso: el día de la última GR más lo que tarda esa ruta. Null si
     * todavía no tiene GR. Necesita `paradas.viajes` cargadas.
     */
    public function llegadaPrevista(): ?CarbonImmutable
    {
        $ultima = $this->paradas->last(fn (ParadaVuelta $parada): bool => $parada->viajes->isNotEmpty());

        return $ultima === null ? null : $this->llegadaEstimada($ultima);
    }

    /**
     * Cuándo llega la unidad al final de la parada: la GR se emite al cargar
     * (la de Ransa, en el Callao), no al llegar. Después de cargar todavía
     * baja a Kio, abastece y recién sube: llega el día de la GR más lo que
     * tarda la ruta.
     */
    public function llegadaEstimada(ParadaVuelta $parada): ?CarbonImmutable
    {
        $salio = $parada->viajes->max(fn (Viaje $viaje): string => $viaje->fecha_traslado->toDateString());

        if ($salio === null) {
            return null;
        }

        // Lo que tarda la ruta principal del tramo, si el catálogo lo dice;
        // si no, lo de siempre entre esas zonas.
        $dias = $this->rutasDe($parada->sentido)->first(fn (RutaVuelta $ruta): bool => ! $ruta->es_complemento)->dias_viaje
            ?? ZonaRuta::diasDeViaje($parada->zona_origen, $parada->zona_destino);

        return CarbonImmutable::parse($salio)->addDays($dias);
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
            'llegada_el' => 'date',
            'km_llegada' => 'integer',
        ];
    }
}
