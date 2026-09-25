<?php

namespace App\Models;

use App\Enums\EstadoCobranza;
use App\Enums\TipoCarga;
use Database\Factories\ViajeFactory;
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
 * @property int|null $factura_id
 * @property Carbon|null $gr_fisica_recibida_at
 * @property-read Factura|null $factura
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
    'factura_id',
    'gr_fisica_recibida_at',
])]
class Viaje extends Model implements HasMedia
{
    /** @use HasFactory<ViajeFactory> */
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
     * La factura que cobra este viaje, cuando ya se emitió. Null mientras esté
     * sin facturar, que es el estado de todo lo que se importa.
     *
     * @return BelongsTo<Factura, $this>
     */
    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class);
    }

    /**
     * En qué punto del cobro está. Requiere `factura` precargada para no caer
     * en N+1 al recorrer un listado.
     */
    public function estadoCobranza(): EstadoCobranza
    {
        return $this->factura?->estado() ?? EstadoCobranza::SinFacturar;
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
    public function nombreCliente(): string
    {
        $delPadron = $this->clienteDelPadron;

        return $delPadron === null ? $this->cliente : $delPadron->alias;
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
        // Las fechas se comparan como número de día y no como Carbon: el
        // tablero llama a esto varias veces sobre cientos de GR, y crear,
        // deduplicar y restar objetos de fecha por cada una era lo que más
        // tardaba en armar la pantalla.
        $diasPorUnidad = [];

        foreach ($viajes as $viaje) {
            $diasPorUnidad[$viaje->identidadUnidad()][$viaje->diaDeTraslado()] = true;
        }

        $grupos = 0;

        foreach ($diasPorUnidad as $dias) {
            $dias = array_keys($dias);
            sort($dias);

            $anterior = null;

            foreach ($dias as $dia) {
                if ($anterior === null || $dia - $anterior > 1) {
                    $grupos++;
                }

                $anterior = $dia;
            }
        }

        return $grupos;
    }

    /**
     * El día de traslado como número de días desde 1970, leído del valor
     * crudo (`Y-m-d`, con o sin hora según el motor) sin pasar por el cast:
     * dos días consecutivos dan números consecutivos.
     */
    private function diaDeTraslado(): int
    {
        return intdiv((int) strtotime(substr((string) $this->attributes['fecha_traslado'], 0, 10).' UTC'), 86400);
    }

    /**
     * La fila del viaje tal como la dibujan los listados. Vive acá y no en un
     * controlador porque `/viajes` y `/contabilidad` muestran exactamente las
     * mismas columnas de operación —la segunda solo añade las de cobranza— y
     * dos copias se desincronizarían a la primera columna nueva.
     *
     * Requiere `tracto`, `carreta`, `conductor`, `clienteDelPadron` y `media`
     * precargadas para no caer en N+1.
     *
     * @return array<string, mixed>
     */
    public function datosDeListado(): array
    {
        return [
            'id' => $this->id,
            'numero_gr' => $this->numero_gr,
            'guias_remitente' => $this->guias_remitente,
            'grupo_viaje' => $this->claveGrupoViaje(),
            'fecha_traslado' => $this->fecha_traslado->toDateString(),
            'placa_tracto' => $this->placa_tracto,
            'placa_carreta' => $this->placa_carreta,
            'tracto_id' => $this->tracto_id,
            'carreta_id' => $this->carreta_id,
            'conductor_nombre' => $this->conductor_nombre,
            'conductor_id' => $this->conductor_id,
            'cliente' => $this->nombreCliente(),
            'destinatario' => $this->destinatario,
            'origen' => $this->origen,
            'origen_ciudad' => $this->ciudadOrigen(),
            'destino' => $this->destino,
            'destino_ciudad' => $this->ciudadDestino(),
            'tipo_carga' => $this->tipo_carga->value,
            'tipo_carga_label' => $this->tipo_carga->label(),
            'peso' => (float) $this->peso,
            'unidad_peso' => $this->unidad_peso,
            'archivo_url' => $this->getFirstMediaUrl('archivo') ?: null,
        ];
    }

    /**
     * Los clientes distintos que aparecen en los viajes, como opciones para un
     * filtro. Sale del texto crudo de la GR y no del padrón porque el filtro
     * tiene que ofrecer también a los que todavía no están dados de alta.
     *
     * Vive acá y no en un controlador porque `/viajes` y `/contabilidad`
     * arman exactamente la misma lista.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function opcionesDeCliente(): array
    {
        return self::query()
            ->select('cliente')
            ->distinct()
            ->orderBy('cliente')
            ->pluck('cliente')
            ->map(fn (string $cliente): array => ['value' => $cliente, 'label' => $cliente])
            ->values()
            ->all();
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
            'gr_fisica_recibida_at' => 'datetime',
            'guias_remitente' => 'array',
            'peso' => 'decimal:3',
            'tipo_carga' => TipoCarga::class,
        ];
    }
}
