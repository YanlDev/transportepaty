<?php

namespace App\Models;

use Database\Factories\UbicacionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Punto del circuito: una ciudad, un centro de acopio o la mina. El catálogo es
 * la pieza sobre la que se apoya todo lo demás —el mapa, la estimación de
 * llegada y la validación de rutas— porque convierte el texto libre que trae el
 * Excel en un punto con posición y con lugar dentro del corredor.
 *
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property string $nombre_normalizado
 * @property float|null $latitud
 * @property float|null $longitud
 * @property bool $es_zona_base
 * @property bool $tiene_taller
 * @property int|null $dias_permanencia_habitual
 * @property int|null $orden_corredor
 * @property bool $es_eje_corredor
 * @property string|null $observaciones
 * @property-read Collection<int, UbicacionAlias> $alias
 */
#[Fillable([
    'codigo',
    'nombre',
    'latitud',
    'longitud',
    'es_zona_base',
    'tiene_taller',
    'dias_permanencia_habitual',
    'orden_corredor',
    'es_eje_corredor',
    'observaciones',
])]
class Ubicacion extends Model
{
    /** @use HasFactory<UbicacionFactory> */
    use HasFactory;

    protected $table = 'ubicaciones';

    /**
     * El nombre normalizado nunca se escribe a mano: es una copia derivada del
     * nombre y se recalcula sola para que no pueda quedar desincronizada.
     */
    protected static function booted(): void
    {
        static::saving(function (Ubicacion $ubicacion): void {
            $ubicacion->nombre_normalizado = self::normalizar($ubicacion->nombre);
        });
    }

    /**
     * Reduce un nombre a su forma comparable: sin tildes, en mayúsculas y sin
     * puntuación. Es lo que hace que «Azángaro», «AZANGARO» y «Azangaro.»
     * caigan todos en el mismo punto sin necesidad de registrar tres alias.
     */
    public static function normalizar(string $texto): string
    {
        $sinPuntuacion = preg_replace('/[^A-Za-z0-9]+/', ' ', Str::ascii($texto)) ?? '';

        return mb_strtoupper(trim($sinPuntuacion));
    }

    /**
     * Textos que aparecen en la casilla de ubicación de los reportes pero que
     * no son lugares: son el estado de la unidad escrito donde no va. Salen de
     * revisar los reportes de un mes.
     *
     * Reconocerlos evita dos cosas: buscarlos en vano contra el catálogo, y
     * dejarlos en la cola de ubicaciones por resolver, donde nadie los va a
     * poder resolver nunca porque no hay nada que resolver.
     *
     * @var list<string>
     */
    public const TEXTOS_SIN_UBICACION = [
        'PROGRAMADO',
        'NO CONTESTA',
        'DESCANSO',
        'VACACIONES',
        'INHABILITADO',
    ];

    /**
     * Indica si el texto de un reporte no describe un lugar. Cubre las marcas
     * de estado y los números sueltos, que son fechas que Excel entrega como
     * serial y alguien pegó en la columna equivocada.
     */
    public static function esTextoSinUbicacion(string $texto): bool
    {
        $normalizado = self::normalizar($texto);

        return $normalizado !== ''
            && (in_array($normalizado, self::TEXTOS_SIN_UBICACION, true)
                || ctype_digit($normalizado));
    }

    /**
     * Nombres alternativos con los que este punto aparece en los reportes.
     *
     * @return HasMany<UbicacionAlias, $this>
     */
    public function alias(): HasMany
    {
        return $this->hasMany(UbicacionAlias::class);
    }

    /**
     * Resuelve un texto de reporte contra el catálogo, primero por nombre y
     * después por alias. Devuelve null cuando el punto no se reconoce: en ese
     * caso la fila se importa igual y queda en la cola por resolver, porque
     * adivinar una ubicación es peor que dejarla vacía.
     */
    public static function buscarPorNombre(string $texto): ?self
    {
        $normalizado = self::normalizar($texto);

        if ($normalizado === '') {
            return null;
        }

        return self::query()->where('nombre_normalizado', $normalizado)->first()
            ?? self::query()->whereHas(
                'alias',
                fn (Builder $alias) => $alias->where('nombre_normalizado', $normalizado),
            )->first();
    }

    /**
     * Registra un nombre alternativo para este punto. Se usa cuando confirmas a
     * mano que un texto que el sistema no reconoció corresponde a esta
     * ubicación; a partir de ahí se resuelve solo.
     */
    public function registrarAlias(string $texto): ?UbicacionAlias
    {
        $normalizado = self::normalizar($texto);

        // Un texto que ya cae en este punto por su propio nombre no necesita
        // alias, y el índice único lo rechazaría si apuntara a otro.
        if ($normalizado === '' || $normalizado === $this->nombre_normalizado) {
            return null;
        }

        return $this->alias()->firstOrCreate(['nombre_normalizado' => $normalizado]);
    }

    /**
     * Indica si el punto pertenece a alguna zona del corredor. Los que no
     * —Cusco, Tacna, Moquegua— son destinos válidos de carga particular, pero
     * quedan fuera del cálculo de avance y de llegada estimada.
     */
    public function estaEnCorredor(): bool
    {
        return $this->orden_corredor !== null;
    }

    /**
     * Indica si el punto cae dentro del tramo que une a las dos ubicaciones
     * indicadas, en cualquiera de los dos sentidos.
     *
     * La comparación es por zona y no por punto exacto porque el corredor no es
     * una fila recta: entre el altiplano y la costa se pasa a veces por La Joya,
     * a veces por Majes y a veces por Yura y Arequipa. Todas comparten zona, así
     * que cualquiera de esas rutas se lee como legítima, y lo que se sigue
     * detectando es la unidad que aparece en una zona por la que ese tramo no
     * pasa —Cusco cuando dice ir de mina a Pisco—, que es el error de verdad.
     */
    public function estaEntre(self $origen, self $destino): bool
    {
        if (! $this->estaEnCorredor() || ! $origen->estaEnCorredor() || ! $destino->estaEnCorredor()) {
            return false;
        }

        return $this->orden_corredor >= min($origen->orden_corredor, $destino->orden_corredor)
            && $this->orden_corredor <= max($origen->orden_corredor, $destino->orden_corredor);
    }

    /**
     * Indica si quedarse la cantidad de días indicada en este punto entra
     * dentro de lo esperable. En Juliaca son uno o dos, porque es la base y
     * ahí está el taller; en un punto de paso como Nazca, ninguno.
     *
     * Es lo que evita que el validador marque como detenida a una unidad que
     * simplemente está esperando turno para volver a subir a mina.
     */
    public function permanenciaEsNormal(int $dias): bool
    {
        return $dias <= ($this->dias_permanencia_habitual ?? 0);
    }

    /**
     * Distancia en línea recta hasta el otro punto, en kilómetros, o null si a
     * alguno le falta posición.
     *
     * Es la distancia geodésica, no la de carretera, así que siempre queda por
     * debajo de los kilómetros reales. Sirve para descartar avances imposibles
     * —si ni en línea recta se llega, por carretera menos— pero no para estimar
     * duraciones, que saldrán del histórico de recorridos.
     */
    public function distanciaKmA(self $otra): ?float
    {
        if (! $this->tieneCoordenadas() || ! $otra->tieneCoordenadas()) {
            return null;
        }

        $radioTierraKm = 6371.0;

        $latitud = deg2rad($otra->latitud - $this->latitud);
        $longitud = deg2rad($otra->longitud - $this->longitud);

        $a = sin($latitud / 2) ** 2
            + cos(deg2rad($this->latitud)) * cos(deg2rad($otra->latitud)) * sin($longitud / 2) ** 2;

        return $radioTierraKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Indica si el punto tiene posición confirmada y puede dibujarse en el mapa.
     */
    public function tieneCoordenadas(): bool
    {
        return $this->latitud !== null && $this->longitud !== null;
    }

    /**
     * Zonas desde las que una unidad descargada puede entrar a la programación
     * de subida a mina.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeZonasBase(Builder $query): void
    {
        $query->where('es_zona_base', true);
    }

    /**
     * Puntos del corredor, por zona desde la mina hacia el norte.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeEnCorredor(Builder $query): void
    {
        $query->whereNotNull('orden_corredor')->orderBy('orden_corredor');
    }

    /**
     * Un punto de referencia por zona: el pueblo grande sobre la carretera.
     * Es lo que se recorre para medir distancias, porque dentro de una zona las
     * alternativas están a pocos kilómetros entre sí y no cambian la cuenta.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeEjesDelCorredor(Builder $query): void
    {
        $query->where('es_eje_corredor', true)
            ->whereNotNull('orden_corredor')
            ->orderBy('orden_corredor');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitud' => 'float',
            'longitud' => 'float',
            'es_zona_base' => 'boolean',
            'tiene_taller' => 'boolean',
            'dias_permanencia_habitual' => 'integer',
            'orden_corredor' => 'integer',
            'es_eje_corredor' => 'boolean',
        ];
    }
}
