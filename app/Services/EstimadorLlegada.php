<?php

namespace App\Services;

use App\Models\EstadoUnidad;
use App\Models\Ubicacion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Estima cuándo llega cada unidad a su destino. No hay GPS de por medio: la
 * cuenta sale del corredor —que es una secuencia fija de puntos— y del ritmo al
 * que la flota lo recorre de verdad, medido sobre el propio historial de
 * estados diarios.
 *
 * Mientras no haya recorridos suficientes se usa una velocidad de referencia, y
 * la estimación se marca como no calibrada para que en pantalla se lea con la
 * desconfianza que merece.
 */
final class EstimadorLlegada
{
    /**
     * Velocidad de referencia en kilómetros de corredor por día, para cuando el
     * histórico todavía no alcanza.
     *
     * Sale de medir los dos tramos largos sobre el catálogo ya corregido por
     * zonas: San Rafael a Pisco son 964 km de corredor y toma de dos a tres
     * días, y Juliaca a Lima 1030 km en un plazo parecido. Eso deja el ritmo
     * real entre 320 y 500 km por día; se toma el medio del rango.
     *
     * Ojo al cambiarla: se aplica sobre distancia de corredor, que es bastante
     * mayor que la línea recta entre extremos.
     */
    public const KILOMETROS_POR_DIA_POR_DEFECTO = 400.0;

    /**
     * Tramos observados que hacen falta para fiarse del histórico antes que de
     * la referencia. Por debajo de esto, un par de viajes raros torcerían la
     * estimación de toda la flota.
     */
    public const RECORRIDOS_MINIMOS = 5;

    /**
     * @param  Collection<int, Ubicacion>  $corredor  Ejes de cada zona, en orden.
     */
    public function __construct(
        private readonly Collection $corredor,
        private readonly float $kilometrosPorDia,
        private readonly bool $calibrado,
    ) {}

    /**
     * Construye el estimador leyendo el corredor y midiendo el ritmo real de la
     * flota sobre el histórico acumulado.
     */
    public static function paraLaFlota(): self
    {
        $corredor = Ubicacion::query()->ejesDelCorredor()->get();

        // Se mide con un estimador provisional porque el ritmo se calcula sobre
        // distancias de corredor, y para eso ya hacen falta los ejes cargados.
        $ritmo = (new self($corredor, self::KILOMETROS_POR_DIA_POR_DEFECTO, false))->medirRitmo();

        return new self(
            $corredor,
            $ritmo ?? self::KILOMETROS_POR_DIA_POR_DEFECTO,
            $ritmo !== null,
        );
    }

    /**
     * Kilómetros de corredor por día que la flota recorre de verdad, o null si
     * todavía no hay recorridos suficientes.
     *
     * Se miran pares de estados consecutivos de una misma unidad y se descartan
     * los que no se movieron: los días parados en base o en taller son reales,
     * pero meterlos en el promedio haría creer que la flota anda más lento de lo
     * que anda cuando está en ruta, y lo que se quiere estimar es el viaje.
     *
     * Se mide en distancia de corredor y no en línea recta a propósito: es la
     * misma unidad con la que después se calcula la llegada, y mezclarlas haría
     * que toda estimación saliera larga.
     *
     * Como consecuencia, los saltos dentro de una misma zona miden cero y no
     * cuentan: son pocos kilómetros y no dicen nada del ritmo de viaje.
     */
    public function medirRitmo(): ?float
    {
        $estados = EstadoUnidad::query()
            ->whereNotNull('ubicacion_id')
            ->with('ubicacion')
            ->orderBy('tracto_id')
            ->orderBy('fecha')
            ->get();

        $kilometros = 0.0;
        $dias = 0;
        $recorridos = 0;

        foreach ($estados->groupBy('tracto_id') as $porUnidad) {
            /** @var Collection<int, EstadoUnidad> $porUnidad */
            $anterior = null;

            foreach ($porUnidad as $estado) {
                if ($anterior !== null && $anterior->ubicacion !== null && $estado->ubicacion !== null) {
                    $distancia = $this->distanciaPorCorredor($anterior->ubicacion, $estado->ubicacion);
                    $transcurridos = $estado->diasDesde($anterior);

                    if ($distancia !== null && $distancia > 0 && $transcurridos > 0) {
                        $kilometros += $distancia;
                        $dias += $transcurridos;
                        $recorridos++;
                    }
                }

                $anterior = $estado;
            }
        }

        if ($recorridos < self::RECORRIDOS_MINIMOS || $dias === 0) {
            return null;
        }

        return $kilometros / $dias;
    }

    /**
     * Cuándo se espera que la unidad llegue a su destino, o null si le falta el
     * destino, la ubicación, o alguno de los dos queda fuera del corredor y por
     * lo tanto no hay trayecto que medir.
     */
    public function estimar(EstadoUnidad $estado): ?Estimacion
    {
        if ($estado->ubicacion === null || $estado->destino === null) {
            return null;
        }

        $distancia = $this->distanciaPorCorredor($estado->ubicacion, $estado->destino);

        if ($distancia === null) {
            return null;
        }

        $dias = (int) ceil($distancia / $this->kilometrosPorDia);

        return new Estimacion(
            diasRestantes: $dias,
            fechaEstimada: Carbon::parse($estado->fecha->toDateString())->addDays($dias)->toDateString(),
            kilometrosRestantes: $distancia,
            calibradaConHistorico: $this->calibrado,
        );
    }

    /**
     * Kilómetros que separan a dos puntos siguiendo el corredor, sumando de eje
     * de zona a eje de zona. Da más que la línea recta entre extremos y se
     * acerca bastante más a la carretera, porque el corredor traza la costa en
     * vez de cruzarla.
     *
     * Se mide entre ejes y no entre los puntos exactos porque dentro de una
     * misma zona las alternativas están a pocos kilómetros entre sí: distinguir
     * si la unidad pasó por Majes o por La Joya no cambia en nada una
     * estimación que se entrega en días.
     */
    public function distanciaPorCorredor(Ubicacion $desde, Ubicacion $hasta): ?float
    {
        if (! $desde->estaEnCorredor() || ! $hasta->estaEnCorredor()) {
            return null;
        }

        $inicio = min($desde->orden_corredor, $hasta->orden_corredor);
        $fin = max($desde->orden_corredor, $hasta->orden_corredor);

        $tramo = $this->corredor
            ->filter(fn (Ubicacion $eje): bool => $eje->orden_corredor >= $inicio
                && $eje->orden_corredor <= $fin)
            ->sortBy('orden_corredor')
            ->values();

        $distancia = 0.0;

        for ($i = 1; $i < $tramo->count(); $i++) {
            $distancia += $tramo[$i - 1]->distanciaKmA($tramo[$i]) ?? 0.0;
        }

        return $distancia;
    }

    public function kilometrosPorDia(): float
    {
        return $this->kilometrosPorDia;
    }

    public function estaCalibrado(): bool
    {
        return $this->calibrado;
    }
}
