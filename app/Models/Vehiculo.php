<?php

namespace App\Models;

use App\Enums\EstadoDocumento;
use App\Enums\EstadoVehiculo;
use App\Enums\SemaforoDocumental;
use App\Enums\TipoCaja;
use App\Enums\TipoDocumento;
use App\Enums\TipoVehiculo;
use Database\Factories\VehiculoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $placa
 * @property string|null $marca
 * @property string|null $modelo
 * @property int|null $anio
 * @property TipoVehiculo $tipo
 * @property EstadoVehiculo $estado
 * @property TipoCaja|null $caja
 * @property string|null $vin
 * @property string|null $numero_motor
 * @property string|null $color
 * @property int|null $ejes
 * @property int|null $peso_neto
 * @property int|null $peso_bruto
 * @property int|null $carga_util
 * @property Carbon|null $fecha_adquisicion
 * @property string|null $observaciones
 */
#[Fillable([
    'placa',
    'marca',
    'modelo',
    'anio',
    'tipo',
    'estado',
    'caja',
    'vin',
    'numero_motor',
    'color',
    'ejes',
    'peso_neto',
    'peso_bruto',
    'carga_util',
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
        'tipo' => TipoVehiculo::Tracto->value,
        'estado' => EstadoVehiculo::Activo->value,
    ];

    /**
     * Las carretas no tienen transmisión, así que se descarta cualquier caja
     * que llegue del formulario cuando el tipo no es tracto.
     */
    protected static function booted(): void
    {
        static::saving(function (Vehiculo $vehiculo): void {
            if (! $vehiculo->tipo->tieneCaja()) {
                $vehiculo->caja = null;
            }
        });
    }

    /**
     * @return HasMany<VehiculoDocumento, $this>
     */
    public function documentos(): HasMany
    {
        return $this->hasMany(VehiculoDocumento::class)->latest();
    }

    /**
     * Busca por placa aceptando el término con o sin guion. Los listados
     * muestran las placas sin separador (`BJF934`), así que lo que el usuario
     * copia de la tabla tiene que encontrar la placa guardada (`BJF-934`).
     *
     * @param  Builder<$this>  $query
     */
    public function scopeWherePlacaLike(Builder $query, string $buscar): void
    {
        $sinSeparadores = preg_replace('/[^A-Za-z0-9]/', '', $buscar) ?? '';

        $query->whereLike('placa', "%{$buscar}%", caseSensitive: false);

        if ($sinSeparadores !== '') {
            // `lower`, `replace` y `like` se comportan igual en PostgreSQL y en
            // SQLite, así que la misma expresión sirve en producción y en tests.
            $query->orWhereRaw(
                "lower(replace(placa, '-', '')) like ?",
                ['%'.mb_strtolower($sinSeparadores).'%'],
            );
        }
    }

    /**
     * El documento del tipo indicado, o null si el vehículo no lo tiene. Cada
     * vehículo guarda como máximo uno de cada tipo (índice único), así que basta
     * con buscarlo en la relación `documentos` ya precargada: evita una segunda
     * consulta por cada tipo que quiera leerse en un listado.
     */
    public function documentoDe(TipoDocumento $tipo): ?VehiculoDocumento
    {
        return $this->documentos->firstWhere('tipo', $tipo);
    }

    /**
     * La habilitación del MTC, conocida como TUC.
     */
    public function tuc(): ?VehiculoDocumento
    {
        return $this->documentoDe(TipoDocumento::HabilitacionMtc);
    }

    /**
     * Estado de la documentación obligatoria del vehículo. Rojo si falta algún
     * documento o si alguno ya venció; ámbar si alguno vence dentro del plazo
     * de aviso; verde si está todo en regla.
     *
     * Los documentos sin fecha de vencimiento —como la tarjeta de propiedad—
     * cuentan como vigentes: basta con tenerlos cargados.
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

        foreach ($this->tipo->documentosObligatorios() as $tipo) {
            $documento = $this->documentoDe($tipo);
            $vence = $documento?->fecha_vencimiento;
            $estado = $documento?->estado() ?? EstadoDocumento::Faltante;

            match ($estado) {
                EstadoDocumento::Faltante => $faltantes[] = $tipo->label(),
                EstadoDocumento::Vencido => $vencidos[] = $tipo->label(),
                EstadoDocumento::PorVencer => $porVencer[] = $tipo->label(),
                EstadoDocumento::Vigente => null,
            };

            $detalle[] = [
                'tipo' => $tipo->value,
                'abreviatura' => $tipo->abreviatura(),
                'label' => $tipo->label(),
                'estado' => $estado->value,
                'estado_label' => $estado->label(),
                'vence' => $vence?->toDateString(),
            ];
        }

        $semaforo = match (true) {
            $faltantes !== [] || $vencidos !== [] => SemaforoDocumental::Rojo,
            $porVencer !== [] => SemaforoDocumental::Ambar,
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
     * El expediente del vehículo en orden fijo: una ranura por cada documento
     * obligatorio, cargada o vacía, y al final los papeles sueltos. Al no
     * depender de qué se haya subido, cada documento ocupa siempre la misma
     * posición y el que falta deja su hueco a la vista en vez de que otro le
     * corra el lugar.
     *
     * @return list<array{tipo: string, abreviatura: string, label: string, estado: string, estado_label: string, obligatorio: bool, documento: array<string, mixed>|null}>
     */
    public function ranurasDocumentales(): array
    {
        $obligatorias = array_map(
            function (array $detalle): array {
                $documento = $this->documentoDe(TipoDocumento::from($detalle['tipo']));

                return [
                    ...$detalle,
                    'obligatorio' => true,
                    'documento' => $documento?->toFrontArray(),
                ];
            },
            $this->estadoDocumental()['documentos'],
        );

        $sueltas = $this->documentos
            ->reject(fn (VehiculoDocumento $documento): bool => $documento->tipo->esObligatorio())
            ->map(fn (VehiculoDocumento $documento): array => [
                'tipo' => $documento->tipo->value,
                'abreviatura' => $documento->tipo->abreviatura(),
                'label' => $documento->nombre ?: $documento->tipo->label(),
                'estado' => $documento->estado()->value,
                'estado_label' => $documento->estado()->label(),
                'vence' => $documento->fecha_vencimiento?->toDateString(),
                'obligatorio' => false,
                'documento' => $documento->toFrontArray(),
            ])
            ->values()
            ->all();

        return [...$obligatorias, ...$sueltas];
    }

    /**
     * Descripción corta para listados: marca y modelo, o la placa si el
     * vehículo aún no tiene esos datos cargados (caso típico de las carretas).
     */
    public function descripcion(): string
    {
        $partes = array_filter([$this->marca, $this->modelo]);

        return $partes === [] ? $this->placa : implode(' ', $partes);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoVehiculo::class,
            'estado' => EstadoVehiculo::class,
            'caja' => TipoCaja::class,
            'anio' => 'integer',
            'ejes' => 'integer',
            'peso_neto' => 'integer',
            'peso_bruto' => 'integer',
            'carga_util' => 'integer',
            'fecha_adquisicion' => 'date:Y-m-d',
        ];
    }
}
