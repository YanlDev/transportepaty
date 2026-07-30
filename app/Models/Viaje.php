<?php

namespace App\Models;

use App\Enums\FaseCiclo;
use App\Enums\TipoCarga;
use App\Enums\TipoGuia;
use Database\Factories\ViajeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Un tramo del circuito realmente hecho: qué unidad, con qué carga, de dónde a
 * dónde y con qué guías de remisión. Es lo que convierte la operación en
 * historia consultable —qué unidad llevó tal guía, qué guías hizo tal placa en
 * junio— y donde viven los archivos de las guías.
 *
 * El conductor y los fierros se guardan copiados y no a través de la asignación
 * vigente: reasignar una carreta hoy no puede cambiar con qué carreta se hizo un
 * viaje del mes pasado.
 *
 * @property int $id
 * @property int $tracto_id
 * @property int|null $carreta_id
 * @property int|null $conductor_id
 * @property TipoCarga $tipo_carga
 * @property FaseCiclo|null $fase
 * @property int $origen_id
 * @property int $destino_id
 * @property Carbon $fecha_salida
 * @property Carbon|null $fecha_llegada
 * @property string|null $numero_guia_remitente
 * @property string|null $numero_guia_transportista
 * @property string|null $observaciones
 * @property-read Vehiculo $tracto
 * @property-read Vehiculo|null $carreta
 * @property-read Conductor|null $conductor
 * @property-read Ubicacion $origen
 * @property-read Ubicacion $destino
 */
#[Fillable([
    'tracto_id',
    'carreta_id',
    'conductor_id',
    'tipo_carga',
    'origen_id',
    'destino_id',
    'fecha_salida',
    'fecha_llegada',
    'numero_guia_remitente',
    'numero_guia_transportista',
    'observaciones',
])]
class Viaje extends Model implements HasMedia
{
    /** @use HasFactory<ViajeFactory> */
    use HasFactory, InteractsWithMedia;

    protected $table = 'viajes';

    /**
     * La fase la determina la carga, igual que en el estado diario. No se pide
     * aparte para que no puedan contradecirse.
     */
    protected static function booted(): void
    {
        static::saving(function (Viaje $viaje): void {
            $viaje->fase = $viaje->tipo_carga->fase();
        });
    }

    public function registerMediaCollections(): void
    {
        foreach (TipoGuia::cases() as $guia) {
            $this->addMediaCollection($guia->coleccion())
                ->singleFile()
                ->acceptsMimeTypes([
                    'image/jpeg',
                    'image/png',
                    'image/webp',
                    'application/pdf',
                    // Las guías electrónicas llegan como XML de SUNAT, y algunos
                    // navegadores lo mandan con uno u otro tipo.
                    'text/xml',
                    'application/xml',
                ]);
        }
    }

    /**
     * Indica si la unidad todavía va en camino.
     */
    public function estaEnCurso(): bool
    {
        return $this->fecha_llegada === null;
    }

    /**
     * Días que tomó el tramo, o los que lleva si sigue en curso.
     */
    public function duracionDias(): int
    {
        return (int) $this->fecha_salida->diffInDays($this->fecha_llegada ?? now());
    }

    /**
     * Número de la guía indicada.
     */
    public function numeroDe(TipoGuia $guia): ?string
    {
        return $this->getAttribute($guia->campoNumero());
    }

    /**
     * Cierra el viaje con la fecha de llegada indicada.
     */
    public function registrarLlegada(string $fecha): bool
    {
        return $this->fill(['fecha_llegada' => $fecha])->save();
    }

    /**
     * Representación de una guía para el frontend: su número y su archivo, o el
     * hueco a la vista cuando todavía falta.
     *
     * @return array{tipo: string, label: string, abreviatura: string, numero: string|null, url: string|null, es_pdf: bool}
     */
    public function guiaComoArray(TipoGuia $guia): array
    {
        $media = $this->getFirstMedia($guia->coleccion());

        return [
            'tipo' => $guia->value,
            'label' => $guia->label(),
            'abreviatura' => $guia->abreviatura(),
            'numero' => $this->numeroDe($guia),
            'url' => $media?->getUrl(),
            'es_pdf' => $media?->mime_type === 'application/pdf',
        ];
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
     * @return BelongsTo<Ubicacion, $this>
     */
    public function origen(): BelongsTo
    {
        return $this->belongsTo(Ubicacion::class, 'origen_id');
    }

    /**
     * @return BelongsTo<Ubicacion, $this>
     */
    public function destino(): BelongsTo
    {
        return $this->belongsTo(Ubicacion::class, 'destino_id');
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeEnCurso(Builder $query): void
    {
        $query->whereNull('fecha_llegada');
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeCompletados(Builder $query): void
    {
        $query->whereNotNull('fecha_llegada');
    }

    /**
     * Busca por número de guía, sin importar cuál de las dos, aceptando el
     * término suelto: quien copia «T001-1234» de un correo y quien recuerda
     * solo «1234» tienen que encontrar el mismo viaje.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeConGuia(Builder $query, string $buscar): void
    {
        $query->where(function (Builder $guias) use ($buscar): void {
            $guias->whereLike('numero_guia_remitente', "%{$buscar}%", caseSensitive: false)
                ->orWhereLike('numero_guia_transportista', "%{$buscar}%", caseSensitive: false);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo_carga' => TipoCarga::class,
            'fase' => FaseCiclo::class,
            'fecha_salida' => 'date:Y-m-d',
            'fecha_llegada' => 'date:Y-m-d',
        ];
    }
}
