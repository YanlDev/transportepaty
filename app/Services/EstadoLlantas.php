<?php

namespace App\Services;

use App\Enums\EstadoLlanta;
use App\Models\Llanta;
use App\Models\MovimientoLlanta;
use App\Models\ParametroMantenimiento;
use App\Models\Vehiculo;
use Illuminate\Support\Collection;

/**
 * Cómo está cada posición de llanta de una o varias unidades. Como el
 * semáforo del preventivo, nada se guarda: sale de las llantas montadas y de
 * la última profundidad anotada de cada una.
 *
 * @phpstan-import-type Posicion from PosicionesLlanta
 *
 * @phpstan-type LlantaMontada array{
 *     id: int,
 *     marca: string|null,
 *     medida: string|null,
 *     codigo: string|null,
 *     condicion: string,
 *     condicion_label: string,
 *     instalada_el: string,
 *     km_instalacion: int|null,
 *     profundidad_mm: float|null,
 *     medida_el: string|null,
 * }
 * @phpstan-type EstadoPosicion array{
 *     codigo: string,
 *     eje: int,
 *     nombre: string,
 *     llanta: LlantaMontada|null,
 *     estado: EstadoLlanta|null,
 * }
 */
class EstadoLlantas
{
    /**
     * @return list<EstadoPosicion>
     */
    public function paraVehiculo(Vehiculo $vehiculo): array
    {
        return $this->paraFlota(collect([$vehiculo]))[$vehiculo->id];
    }

    /**
     * Con un puñado de consultas en total, no por unidad. Una posición vacía queda con
     * `llanta` y `estado` en null.
     *
     * @param  Collection<int, Vehiculo>  $vehiculos
     * @return array<int, list<EstadoPosicion>>
     */
    public function paraFlota(Collection $vehiculos): array
    {
        $montadas = Llanta::query()
            ->montadas()
            ->whereIn('vehiculo_id', $vehiculos->pluck('id'))
            ->get();

        $ultimasMedidas = MovimientoLlanta::query()
            ->whereIn('llanta_id', $montadas->pluck('id'))
            ->whereNotNull('profundidad_mm')
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get(['llanta_id', 'fecha', 'profundidad_mm'])
            ->unique('llanta_id')
            ->keyBy('llanta_id');

        $parametros = ParametroMantenimiento::vigentes();
        $porVehiculo = $montadas->groupBy('vehiculo_id');
        $resultado = [];

        foreach ($vehiculos as $vehiculo) {
            $enUnidad = $porVehiculo->get($vehiculo->id, collect())->keyBy('posicion');

            $resultado[$vehiculo->id] = array_map(function (array $posicion) use ($enUnidad, $ultimasMedidas, $parametros): array {
                $llanta = $enUnidad->get($posicion['codigo']);

                if ($llanta === null) {
                    return [...$posicion, 'llanta' => null, 'estado' => null];
                }

                $medida = $ultimasMedidas->get($llanta->id);
                $profundidad = $medida === null ? null : (float) $medida->profundidad_mm;

                return [
                    ...$posicion,
                    'llanta' => [
                        'id' => $llanta->id,
                        'marca' => $llanta->marca,
                        'medida' => $llanta->medida,
                        'codigo' => $llanta->codigo,
                        'condicion' => $llanta->condicion->value,
                        'condicion_label' => $llanta->condicion->label(),
                        'instalada_el' => $llanta->instalada_el->toDateString(),
                        'km_instalacion' => $llanta->km_instalacion,
                        'profundidad_mm' => $profundidad,
                        'medida_el' => $medida?->fecha->toDateString(),
                    ],
                    'estado' => $parametros->estadoLlanta($profundidad),
                ];
            }, PosicionesLlanta::para($vehiculo->tipo));
        }

        return $resultado;
    }
}
