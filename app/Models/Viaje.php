<?php

namespace App\Models;

use App\Enums\EstadoGre;
use App\Enums\MotivoTraslado;
use App\Enums\TipoCarga;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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
 * @property int|null $cliente_id
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
 * @property int|null $punto_partida_id
 * @property int|null $punto_llegada_id
 * @property MotivoTraslado $motivo_traslado
 * @property EstadoGre $gre_estado
 * @property string|null $gre_ticket
 * @property array<string, mixed>|null $gre_respuesta
 * @property-read PuntoTraslado|null $puntoPartida
 * @property-read PuntoTraslado|null $puntoLlegada
 * @property-read Vehiculo|null $tracto
 * @property-read Vehiculo|null $carreta
 * @property-read Conductor|null $conductor
 * @property-read Cliente|null $clienteDelPadron
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
    'cliente_id',
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
    'punto_partida_id',
    'punto_llegada_id',
    'motivo_traslado',
    'gre_estado',
    'gre_ticket',
    'gre_respuesta',
])]
class Viaje extends Model implements HasMedia
{
    use HasFactory;
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
     * El cliente del padrón, cuando el RUC de la GR matcheó contra uno dado
     * de alta. Se llama distinto que la columna `cliente` —que es el texto
     * crudo de la GR— para no pisarla.
     *
     * @return BelongsTo<Cliente, $this>
     */
    public function clienteDelPadron(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    /**
     * Cómo se muestra el cliente: el alias del padrón si el RUC matcheó, y si
     * no el texto tal cual vino en la GR. Así las tablas y los gráficos usan
     * «Porcelanato Latino» en vez de «PORCELANATO LATINO SOCIEDAD ANONIMA
     * CERRADA», sin perder de vista al cliente que todavía no está dado de
     * alta.
     *
     * Requiere `clienteDelPadron` precargada para no caer en N+1.
     */
    /**
     * Punto de partida declarado ante SUNAT. Es distinto de `origen`: ese es
     * el texto que traía el PDF importado, este es el catálogo con ubigeo.
     *
     * @return BelongsTo<PuntoTraslado, $this>
     */
    public function puntoPartida(): BelongsTo
    {
        return $this->belongsTo(PuntoTraslado::class, 'punto_partida_id');
    }

    /**
     * @return BelongsTo<PuntoTraslado, $this>
     */
    public function puntoLlegada(): BelongsTo
    {
        return $this->belongsTo(PuntoTraslado::class, 'punto_llegada_id');
    }

    public function nombreCliente(): string
    {
        return $this->clienteDelPadron?->alias ?? $this->cliente;
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
     * Tracto + carreta + conductor, sin la fecha — identifica la unidad que
     * hizo el viaje. Separado de `claveGrupoViaje()` para poder agrupar
     * tolerando que el mismo viaje cruce a un segundo día (ver
     * `contarViajesReales()`), sin duplicar la lógica de fallback a
     * placa/DNI crudos cuando no matchean contra el padrón.
     */
    public function identidadUnidad(): string
    {
        return implode('|', [
            $this->tracto_id !== null ? "id:{$this->tracto_id}" : "placa:{$this->placa_tracto}",
            $this->carreta_id !== null
                ? "id:{$this->carreta_id}"
                : ($this->placa_carreta !== null ? "placa:{$this->placa_carreta}" : 'sin-carreta'),
            $this->conductor_id !== null ? "id:{$this->conductor_id}" : "dni:{$this->conductor_dni}",
        ]);
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
     *
     * Solo agrupa por día EXACTO — para un conteo que tolera que el mismo
     * viaje cruce a un segundo día consecutivo, usar `contarViajesReales()`.
     */
    public function claveGrupoViaje(): string
    {
        return $this->fecha_traslado->toDateString().'|'.$this->identidadUnidad();
    }

    /**
     * Cuántos viajes reales hay en una colección de GR — no cuántas filas.
     * A diferencia de `claveGrupoViaje()` (día exacto, usada para agrupar
     * visualmente la tabla de `/viajes`), acá se tolera que el mismo viaje
     * cruce a un segundo día consecutivo: se vio en casos reales (ej. Mur-Wy)
     * donde la misma unidad+conductor trae GR de dos días seguidos por una
     * sola salida. Agrupa por unidad y, dentro de cada una, funde fechas que
     * quedan a lo sumo 1 día de diferencia entre sí.
     *
     * Mismo trade-off que `claveGrupoViaje()`: puede fundir dos viajes reales
     * distintos si la misma unidad+conductor salió dos días seguidos por
     * separado — se acepta porque el caso que sí queríamos resolver (un
     * viaje partido en dos fechas) es más común que ese falso positivo.
     *
     * @param  Collection<int, self>  $viajes
     */
    public static function contarViajesReales(Collection $viajes): int
    {
        return $viajes
            ->groupBy(fn (self $viaje): string => $viaje->identidadUnidad())
            ->sum(function (Collection $porUnidad): int {
                $fechas = $porUnidad->pluck('fecha_traslado')->unique()->sort()->values();

                $grupos = 0;
                $anterior = null;

                foreach ($fechas as $fecha) {
                    if ($anterior === null || $anterior->diffInDays($fecha) > 1) {
                        $grupos++;
                    }

                    $anterior = $fecha;
                }

                return $grupos;
            });
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('archivo')
            ->singleFile()
            ->acceptsMimeTypes(['application/pdf']);
    }

    /**
     * Los mismos valores por defecto que la migración, para que un viaje recién
     * creado ya los tenga en memoria sin releerlo de la base.
     *
     * @var array<string, string>
     */
    protected $attributes = [
        'motivo_traslado' => MotivoTraslado::TrasladoEntreEstablecimientos->value,
        'gre_estado' => EstadoGre::Pendiente->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_emision' => 'datetime',
            'fecha_traslado' => 'date:Y-m-d',
            'guias_remitente' => 'array',
            'gre_respuesta' => 'array',
            'gre_estado' => EstadoGre::class,
            'motivo_traslado' => MotivoTraslado::class,
            'peso' => 'decimal:3',
            'tipo_carga' => TipoCarga::class,
        ];
    }
}
