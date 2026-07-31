<?php

namespace App\Models;

use App\Enums\Cliente;
use App\Enums\EstadoCarga;
use App\Enums\FaseCiclo;
use App\Enums\OrigenDato;
use App\Enums\TipoCarga;
use Database\Factories\EstadoUnidadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Dónde está una unidad y qué lleva, un registro por día. Es la fuente de
 * verdad de la operación: el Excel es apenas una de las formas de alimentarla,
 * y el mapa, la llegada estimada y la programación leen de acá, nunca del
 * archivo.
 *
 * @property int $id
 * @property Carbon $fecha
 * @property int $tracto_id
 * @property int|null $carreta_id
 * @property int|null $conductor_id
 * @property TipoCarga|null $tipo_carga
 * @property EstadoCarga|null $estado_carga
 * @property Cliente|null $cliente
 * @property FaseCiclo|null $fase
 * @property string|null $origen
 * @property string|null $destino
 * @property string|null $ubicacion
 * @property array<string, string>|null $origenes
 * @property string|null $observaciones
 * @property string|null $proximas_paradas
 * @property Carbon|null $fecha_disponible
 * @property-read Vehiculo $tracto
 * @property-read Vehiculo|null $carreta
 * @property-read Conductor|null $conductor
 */
#[Fillable([
    'fecha',
    'tracto_id',
    'carreta_id',
    'conductor_id',
    'tipo_carga',
    'estado_carga',
    'cliente',
    'fase',
    'origen',
    'destino',
    'ubicacion',
    'observaciones',
    'proximas_paradas',
    'fecha_disponible',
])]
class EstadoUnidad extends Model
{
    /** @use HasFactory<EstadoUnidadFactory> */
    use HasFactory;

    protected $table = 'estados_unidad';

    /**
     * Campos que la carga determina por completo y que nadie puede contradecir,
     * ni a mano: «vacío» y «descargada» son la misma cosa dicha de dos maneras,
     * y el tramo del circuito sale de la carga sin margen de interpretación.
     * Se recalculan en cada guardado pase lo que pase.
     *
     * @var list<string>
     */
    public const DERIVADOS_FIJOS = ['estado_carga', 'fase'];

    /**
     * Campos que la carga sugiere pero que sí admiten corrección: un flete de
     * materiales podría no ser de Minsur, y esa excepción hay que poder
     * anotarla.
     *
     * @var list<string>
     */
    public const DERIVADOS_CORREGIBLES = ['cliente'];

    /**
     * Campos que pasan a estar confirmados a mano cuando alguien los edita
     * directamente. A partir de ahí ninguna deducción ni importación posterior
     * los vuelve a tocar.
     *
     * @var list<string>
     */
    public const CAMPOS_CONFIRMABLES = [
        'carreta_id',
        'conductor_id',
        'tipo_carga',
        'cliente',
        'origen',
        'destino',
        'ubicacion',
    ];

    /**
     * Mantiene los campos derivados en línea con el tipo de carga en cada
     * guardado, salvo los que fueron confirmados a mano. Hacerlo acá y no en
     * quien escribe garantiza que ningún camino —importación, formulario o
     * consola— pueda dejar el registro contradiciéndose.
     */
    protected static function booted(): void
    {
        static::saving(function (EstadoUnidad $estado): void {
            $estado->deducirDerivados();
        });
    }

    /**
     * Rellena estado de carga, cliente y fase a partir del tipo de carga.
     *
     * Los fijos se reescriben siempre, aunque alguien los haya marcado a mano:
     * una unidad que sube vacía de Juliaca a mina no puede figurar como cargada,
     * y admitir esa corrección solo permitiría guardar filas que se contradicen
     * solas. El cliente sí respeta lo que hayas confirmado.
     */
    public function deducirDerivados(): void
    {
        if ($this->tipo_carga === null) {
            return;
        }

        $valores = [
            'estado_carga' => $this->tipo_carga->estadoCarga(),
            'fase' => $this->tipo_carga->fase(),
            'cliente' => $this->tipo_carga->cliente(),
        ];

        foreach ($valores as $campo => $valor) {
            if (in_array($campo, self::DERIVADOS_CORREGIBLES, true) && $this->esManual($campo)) {
                continue;
            }

            $this->setAttribute($campo, $valor);
            $this->marcarOrigen($campo, OrigenDato::Deducido);
        }
    }

    /**
     * Procedencia registrada para el campo, o null si nunca se anotó.
     */
    public function origenDe(string $campo): ?OrigenDato
    {
        $origen = $this->origenes[$campo] ?? null;

        return $origen === null ? null : OrigenDato::tryFrom($origen);
    }

    /**
     * Anota de dónde salió el valor de un campo.
     */
    public function marcarOrigen(string $campo, OrigenDato $origen): static
    {
        $this->origenes = [...$this->origenes ?? [], $campo => $origen->value];

        return $this;
    }

    /**
     * Marca campos como confirmados por una persona. A partir de ahí ninguna
     * importación ni deducción los vuelve a tocar.
     *
     * @param  list<string>  $campos
     */
    public function confirmar(array $campos): static
    {
        foreach ($campos as $campo) {
            $this->marcarOrigen($campo, OrigenDato::Manual);
        }

        return $this;
    }

    public function esManual(string $campo): bool
    {
        return $this->origenDe($campo) === OrigenDato::Manual;
    }

    /**
     * Indica si el campo puede recalcularse o sobrescribirse al reimportar.
     */
    public function admiteSobrescritura(string $campo): bool
    {
        return $this->origenDe($campo)?->admiteSobrescritura() ?? true;
    }

    /**
     * Estado inmediatamente anterior de la misma unidad. Es contra este que se
     * comparan las transiciones, y no contra «ayer»: si hubo fin de semana o un
     * día sin reporte, el salto se juzga con los días que de verdad pasaron.
     */
    public function anterior(): ?self
    {
        return self::query()
            ->where('tracto_id', $this->tracto_id)
            // Comparado como texto de fecha y no como Carbon: al serializarse
            // con hora, «2026-07-21» resultaría menor que «2026-07-21 00:00:00»
            // y el estado se encontraría a sí mismo.
            ->where('fecha', '<', $this->fecha->toDateString())
            ->orderByDesc('fecha')
            ->first();
    }

    /**
     * Último estado conocido de cada unidad antes de la fecha indicada, en dos
     * consultas en vez de una por unidad. Es lo que necesita el validador de
     * transiciones para juzgar un día entero, y lo que permite arrastrar el día
     * anterior sin recorrer la tabla completa.
     *
     * @param  array<int>|null  $tractoIds  Null para todas las unidades.
     * @param  list<string>  $relaciones
     * @return Collection<int, self>
     */
    public static function ultimosAntesDe(string $fecha, ?array $tractoIds = null, array $relaciones = []): Collection
    {
        $ultimas = self::query()
            ->selectRaw('tracto_id, max(fecha) as ultima')
            ->where('fecha', '<', $fecha)
            ->when($tractoIds !== null, fn (Builder $query) => $query->whereIn('tracto_id', $tractoIds))
            ->groupBy('tracto_id')
            ->pluck('ultima', 'tracto_id');

        if ($ultimas->isEmpty()) {
            return new Collection;
        }

        return self::query()
            ->with($relaciones)
            ->where(function (Builder $query) use ($ultimas): void {
                foreach ($ultimas as $tractoId => $ultima) {
                    $query->orWhere(fn (Builder $fila) => $fila
                        ->where('tracto_id', $tractoId)
                        ->where('fecha', $ultima));
                }
            })
            ->get()
            ->keyBy('tracto_id');
    }

    /**
     * Días transcurridos desde el estado indicado.
     */
    public function diasDesde(self $anterior): int
    {
        return (int) $anterior->fecha->diffInDays($this->fecha);
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
            'fecha_disponible' => 'date:Y-m-d',
            'tipo_carga' => TipoCarga::class,
            'estado_carga' => EstadoCarga::class,
            'cliente' => Cliente::class,
            'fase' => FaseCiclo::class,
            'origenes' => 'array',
        ];
    }
}
