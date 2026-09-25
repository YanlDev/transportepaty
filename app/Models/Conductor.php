<?php

namespace App\Models;

use App\Enums\EstadoDocumento;
use App\Enums\SemaforoDocumental;
use App\Enums\TipoDocumentoConductor;
use App\Services\RelojOperativo;
use Carbon\CarbonInterface;
use Database\Factories\ConductorFactory;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $nombres
 * @property string $apellidos
 * @property string $documento
 * @property string|null $licencia
 * @property string|null $categoria_licencia
 * @property Carbon|null $licencia_vence
 * @property string|null $telefono
 * @property string|null $telefono_alterno
 * @property string|null $email
 * @property Carbon|null $fecha_nacimiento
 * @property string|null $procedencia
 * @property bool $activo
 *
 * `CarbonInterface` y no `Carbon`: la aplicación resuelve las fechas a
 * `CarbonImmutable` (ver `AppServiceProvider`), que no desciende de
 * `Illuminate\Support\Carbon`, y acá además se asigna en el `saving`.
 * @property CarbonInterface|null $fecha_baja
 * @property string|null $motivo_baja
 * @property-read string $nombre_completo
 */
#[Fillable([
    'user_id',
    'nombres',
    'apellidos',
    'documento',
    'licencia',
    'categoria_licencia',
    'licencia_vence',
    'telefono',
    'telefono_alterno',
    'email',
    'fecha_nacimiento',
    'procedencia',
    'activo',
    'fecha_baja',
    'motivo_baja',
])]
#[Appends(['nombre_completo'])]
class Conductor extends Model
{
    /** @use HasFactory<ConductorFactory> */
    use HasFactory;

    protected $table = 'conductores';

    /**
     * La baja es un hecho puntual (cuándo y por qué se fue), no dos campos
     * sueltos que alguien puede dejar desalineados: al reactivar se limpian
     * juntos, y al dar de baja sin fecha explícita se asume hoy —así ningún
     * conductor inactivo queda sin fecha de baja para los reportes que
     * comparan headcount mes a mes.
     */
    protected static function booted(): void
    {
        static::saving(function (Conductor $conductor): void {
            if ($conductor->activo) {
                $conductor->fecha_baja = null;
                $conductor->motivo_baja = null;
            } elseif ($conductor->isDirty('activo') && $conductor->fecha_baja === null) {
                $conductor->fecha_baja = RelojOperativo::fechaDeHoy();
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<ConductorDocumento, $this>
     */
    public function documentos(): HasMany
    {
        return $this->hasMany(ConductorDocumento::class);
    }

    /**
     * @return HasMany<Asistencia, $this>
     */
    public function asistencias(): HasMany
    {
        return $this->hasMany(Asistencia::class);
    }

    /**
     * Solo los viajes que matchearon contra este conductor en el padrón
     * (`conductor_id` no nulo): los que llegaron con un DNI que no matcheó
     * quedan como texto crudo en la GR y no pertenecen a nadie en este
     * historial.
     *
     * @return HasMany<Viaje, $this>
     */
    public function viajes(): HasMany
    {
        return $this->hasMany(Viaje::class);
    }

    /**
     * Busca por número de documento ignorando los ceros a la izquierda: el
     * DNI que trae la GR viene con sus ocho dígitos («02301443») y el del
     * padrón a veces se cargó desde una hoja de cálculo que se comió el cero
     * («2301443»). Son el mismo documento, así que deben matchear igual.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeWhereDocumento(Builder $query, string $documento): void
    {
        $sinCeros = ltrim($documento, '0');

        if ($sinCeros === '') {
            $query->where('documento', $documento);

            return;
        }

        // `ltrim` con lista de caracteres se comporta igual en PostgreSQL y en
        // SQLite, así que la misma expresión sirve en producción y en tests.
        $query->whereRaw("ltrim(documento, '0') = ?", [$sinCeros]);
    }

    /**
     * El documento del tipo indicado, o null si no lo tiene. Cada conductor
     * guarda como máximo uno de cada tipo (índice único), así que basta con
     * buscarlo en la relación `documentos` ya precargada.
     */
    public function documentoDe(TipoDocumentoConductor $tipo): ?ConductorDocumento
    {
        return $this->documentos->firstWhere('tipo', $tipo);
    }

    /**
     * Estado de la documentación obligatoria del conductor. Rojo si falta algún
     * documento o si venció alguno de los que inhabilitan; ámbar si alguno
     * vence dentro del plazo de aviso, o si caducó el DNI —que se avisa pero
     * no saca a nadie de ruta (ver `TipoDocumentoConductor::vencimientoInhabilita`)—;
     * verde si está todo en regla.
     *
     * `vencidos` los lista a todos igual, caduque lo que caduque: la ficha
     * tiene que mostrar el DNI vencido aunque el semáforo no se ponga rojo por
     * él.
     *
     * Requiere la relación `documentos` precargada para no caer en N+1.
     *
     * @return array{
     *     semaforo: string,
     *     faltantes: list<string>,
     *     vencidos: list<string>,
     *     por_vencer: list<string>,
     *     documentos: list<array{tipo: string, abreviatura: string, label: string, estado: string, estado_label: string, vence: string|null}>
     * }
     */
    public function estadoDocumental(): array
    {
        $faltantes = [];
        $vencidos = [];
        $porVencer = [];
        $detalle = [];

        // Los vencidos que sí sacan de ruta. Se cuentan aparte de `$vencidos`
        // porque esa lista es para mostrar y esta para decidir el color.
        $inhabilitan = [];

        foreach (TipoDocumentoConductor::obligatorios() as $tipo) {
            $documento = $this->documentoDe($tipo);
            $estado = $documento?->estado() ?? EstadoDocumento::Faltante;

            match ($estado) {
                EstadoDocumento::Faltante => $faltantes[] = $tipo->label(),
                EstadoDocumento::Vencido => $vencidos[] = $tipo->label(),
                EstadoDocumento::PorVencer => $porVencer[] = $tipo->label(),
                EstadoDocumento::Vigente => null,
            };

            if ($estado === EstadoDocumento::Vencido && $tipo->vencimientoInhabilita()) {
                $inhabilitan[] = $tipo->label();
            }

            $detalle[] = [
                'tipo' => $tipo->value,
                'abreviatura' => $tipo->abreviatura(),
                'label' => $tipo->label(),
                'estado' => $estado->value,
                'estado_label' => $estado->label(),
                'vence' => $documento?->fecha_vencimiento?->toDateString(),
            ];
        }

        $semaforo = match (true) {
            $faltantes !== [] || $inhabilitan !== [] => SemaforoDocumental::Rojo,
            $porVencer !== [] || $vencidos !== [] => SemaforoDocumental::Ambar,
            default => SemaforoDocumental::Verde,
        };

        return [
            'semaforo' => $semaforo->value,
            'faltantes' => $faltantes,
            'vencidos' => $vencidos,
            'por_vencer' => $porVencer,
            'documentos' => $detalle,
        ];
    }

    /**
     * El expediente del conductor en orden fijo: una ranura por cada documento
     * obligatorio, cargada o vacía, y al final los papeles sueltos. El que falta
     * deja su hueco a la vista en vez de que otro le corra el lugar.
     *
     * @return list<array{tipo: string, abreviatura: string, label: string, estado: string, estado_label: string, obligatorio: bool, documento: array<string, mixed>|null}>
     */
    public function ranurasDocumentales(): array
    {
        $obligatorias = array_map(
            function (array $detalle): array {
                $documento = $this->documentoDe(TipoDocumentoConductor::from($detalle['tipo']));

                return [
                    ...$detalle,
                    'obligatorio' => true,
                    'documento' => $documento?->toFrontArray(),
                ];
            },
            $this->estadoDocumental()['documentos'],
        );

        $sueltas = $this->documentos
            ->reject(fn (ConductorDocumento $documento): bool => $documento->tipo->esObligatorio())
            ->map(fn (ConductorDocumento $documento): array => [
                'tipo' => $documento->tipo->value,
                'abreviatura' => $documento->tipo->abreviatura(),
                'label' => $documento->tipo->label(),
                'estado' => $documento->estado()->value,
                'estado_label' => $documento->estado()->label(),
                'obligatorio' => false,
                'documento' => $documento->toFrontArray(),
            ])
            ->values()
            ->all();

        return [...$obligatorias, ...$sueltas];
    }

    /**
     * @return Attribute<string, never>
     */
    protected function nombreCompleto(): Attribute
    {
        return Attribute::get(fn (): string => trim("{$this->nombres} {$this->apellidos}"));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'licencia_vence' => 'date:Y-m-d',
            'fecha_nacimiento' => 'date:Y-m-d',
            'fecha_baja' => 'date:Y-m-d',
            'activo' => 'boolean',
        ];
    }
}
