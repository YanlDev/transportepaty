<?php

namespace App\Models;

use App\Enums\TipoCarga;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Un viaje registrado a partir de la GR-transportista que Paty emite. Una fila
 * por documento subido: la fuente de verdad es la propia GR, así que no hay
 * captura manual de estos datos ni una tabla borrador — lo que llega del PDF
 * se guarda directo (nada bloquea la importación), y lo que no matchea contra
 * el padrón queda como texto crudo con el FK en null.
 *
 * @property int $id
 * @property string $numero_gr
 * @property Carbon $fecha_emision
 * @property Carbon $fecha_traslado
 * @property string $origen
 * @property string $destino
 * @property TipoCarga $tipo_carga
 * @property string $cliente
 * @property string|null $cliente_ruc
 * @property string $destinatario
 * @property string|null $destinatario_ruc
 * @property array<int, array{numero: string, ruc: string}>|null $guias_remitente
 * @property float $peso
 * @property string $unidad_peso
 * @property string $placa_tracto
 * @property string|null $placa_carreta
 * @property int|null $tracto_id
 * @property int|null $carreta_id
 * @property string $conductor_nombre
 * @property string|null $conductor_dni
 * @property int|null $conductor_id
 * @property string|null $observaciones
 * @property-read Vehiculo|null $tracto
 * @property-read Vehiculo|null $carreta
 * @property-read Conductor|null $conductor
 */
#[Fillable([
    'numero_gr',
    'fecha_emision',
    'fecha_traslado',
    'origen',
    'destino',
    'tipo_carga',
    'cliente',
    'cliente_ruc',
    'destinatario',
    'destinatario_ruc',
    'guias_remitente',
    'peso',
    'unidad_peso',
    'placa_tracto',
    'placa_carreta',
    'tracto_id',
    'carreta_id',
    'conductor_nombre',
    'conductor_dni',
    'conductor_id',
    'observaciones',
])]
class Viaje extends Model implements HasMedia
{
    use InteractsWithMedia;

    /**
     * Incluye tractos y carretas dados de baja (soft delete): el historial de
     * viajes debe seguir mostrando con qué fierro se hizo aunque ya no exista
     * en la flota.
     *
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
     * La ciudad (distrito) de origen/destino, para mostrar en los listados
     * sin ocupar el espacio de la dirección completa. El texto de la GR
     * siempre trae el patrón `... - distrito - provincia - departamento` —
     * no hay catálogo de ubicaciones detrás, así que se deriva del texto en
     * vez de resolverse contra algo. Con menos segmentos de los esperados
     * (dirección corta o atípica) cae al último disponible en vez de fallar.
     */
    /**
     * Estática (no lee `$this`) para poder usarla también fuera de una
     * instancia real — ej. armar la lista de ciudades para el filtro del
     * listado, sin tener que instanciar un `Viaje` por cada dirección.
     */
    public static function ciudadDesde(string $direccion): string
    {
        $partes = array_map('trim', explode(' - ', $direccion));

        if (count($partes) >= 4) {
            return $partes[count($partes) - 3];
        }

        return (string) end($partes);
    }

    public function ciudadOrigen(): string
    {
        return self::ciudadDesde($this->origen);
    }

    public function ciudadDestino(): string
    {
        return self::ciudadDesde($this->destino);
    }

    /**
     * Cada fila acá es una GR, pero un solo viaje físico puede traer más de
     * una (ej. el mismo camión sale una vez y lleva carga de dos clientes
     * distintos, cada una con su propia GR) — la GR no tiene un campo que
     * diga «este es el mismo viaje que aquella otra», así que se infiere:
     * mismo tracto + carreta + conductor + día de traslado es, en la
     * práctica, la misma salida del camión.
     *
     * Es una heurística, no una clave real: dos salidas distintas del mismo
     * camión con el mismo conductor el mismo día caerían en el mismo grupo
     * aunque sean viajes distintos. Se acepta el riesgo porque es el caso
     * raro; si tracto/carreta no matchearon contra el padrón (id null), cae
     * a la placa cruda para que igual agrupe.
     */
    public function claveGrupoViaje(): string
    {
        return implode('|', [
            $this->fecha_traslado->toDateString(),
            $this->tracto_id !== null ? "id:{$this->tracto_id}" : "placa:{$this->placa_tracto}",
            $this->carreta_id !== null
                ? "id:{$this->carreta_id}"
                : ($this->placa_carreta !== null ? "placa:{$this->placa_carreta}" : 'sin-carreta'),
            $this->conductor_id !== null ? "id:{$this->conductor_id}" : "dni:{$this->conductor_dni}",
        ]);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('archivo')
            ->singleFile()
            ->acceptsMimeTypes(['application/pdf']);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_emision' => 'datetime',
            'fecha_traslado' => 'date:Y-m-d',
            'guias_remitente' => 'array',
            'peso' => 'decimal:3',
            'tipo_carga' => TipoCarga::class,
        ];
    }
}
