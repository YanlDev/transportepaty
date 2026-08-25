<?php

namespace App\Models;

use App\Enums\EstadoDocumento;
use App\Enums\SemaforoDocumental;
use App\Enums\TipoDocumentoConductor;
use Database\Factories\ConductorFactory;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
 * @property string|null $email
 * @property Carbon|null $fecha_nacimiento
 * @property string|null $procedencia
 * @property bool $activo
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
    'email',
    'fecha_nacimiento',
    'procedencia',
    'activo',
])]
#[Appends(['nombre_completo'])]
class Conductor extends Model
{
    /** @use HasFactory<ConductorFactory> */
    use HasFactory;

    protected $table = 'conductores';

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
     * documento o si alguno ya venció; ámbar si alguno vence dentro del plazo de
     * aviso; verde si está todo en regla.
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

        foreach (TipoDocumentoConductor::obligatorios() as $tipo) {
            $documento = $this->documentoDe($tipo);
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
                'vence' => $documento?->fecha_vencimiento?->toDateString(),
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
            'activo' => 'boolean',
        ];
    }
}
