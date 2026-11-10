<?php

namespace App\Services;

use App\Enums\EstadoEquipamiento;
use App\Enums\TipoMovimientoEquipamiento;
use App\Models\ConteoEquipamiento;
use App\Models\DetalleIngreso;
use App\Models\DetalleSalida;
use App\Models\ItemEquipamiento;
use App\Models\MovimientoEquipamiento;
use App\Models\SalidaAlmacen;
use App\Models\Vehiculo;
use App\Models\VencimientoEquipamiento;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * El equipamiento que cada unidad tiene asignado: fajas en la carreta;
 * extintor, botiquín, conos y herramientas en el tracto.
 *
 * Nada se guarda como contador. Lo asignado de un ítem sale de:
 *
 * - su **saldo inicial**: lo que la unidad ya tenía en su primer conteo;
 * - lo que **salió del almacén** con un vale a nombre de la unidad (menos lo
 *   devuelto): no se anota otra vez, se lee de los vales;
 * - lo que **llegó o se fue** en un traslado entre unidades;
 * - menos lo que se dio de **baja**.
 *
 * El conteo ya no dice cuánto tiene la unidad: lo comprueba. Si encuentra
 * dañadas, no cuentan para el mínimo hasta que se den de baja; si encuentra
 * menos de lo asignado, queda a la vista como «sin explicar».
 *
 * @phpstan-type FilaEquipamiento array{
 *     id: int,
 *     nombre: string,
 *     articulo_id: int|null,
 *     lleva_vencimiento: bool,
 *     minimo: int|null,
 *     asignado: int,
 *     buenas: int|null,
 *     danadas: int|null,
 *     sin_explicar: int,
 *     faltan: int,
 *     con_historial: bool,
 *     vence_el: string|null,
 *     vencido: bool,
 *     por_vencer: bool,
 * }
 * @phpstan-type EstadoUnidadEquipada array{
 *     conteo: array{id: int, fecha: string}|null,
 *     items: list<FilaEquipamiento>,
 *     faltan: int,
 *     vencidos: int,
 *     estado: EstadoEquipamiento,
 * }
 */
class EquipamientoDeUnidades
{
    /** Con cuántos días de anticipación se avisa un vencimiento. */
    public const AVISO_DIAS = 30;

    /**
     * @return EstadoUnidadEquipada
     */
    public function paraVehiculo(Vehiculo $vehiculo): array
    {
        return $this->paraFlota(collect([$vehiculo]))[$vehiculo->id];
    }

    /**
     * Cuánto tiene asignado la unidad de ese ítem.
     */
    public function asignado(Vehiculo $vehiculo, ItemEquipamiento $item): int
    {
        foreach ($this->paraVehiculo($vehiculo)['items'] as $fila) {
            if ($fila['id'] === $item->id) {
                return $fila['asignado'];
            }
        }

        return 0;
    }

    /**
     * Con un puñado de consultas en total, no por unidad.
     *
     * @param  Collection<int, Vehiculo>  $vehiculos
     * @return array<int, EstadoUnidadEquipada>
     */
    public function paraFlota(Collection $vehiculos): array
    {
        $ids = array_values($vehiculos->map(fn (Vehiculo $vehiculo): int => $vehiculo->id)->all());
        $items = ItemEquipamiento::query()->vigentes()->get();
        $hoy = RelojOperativo::fechaDeHoy();

        $ultimos = ConteoEquipamiento::query()
            ->whereIn('vehiculo_id', $ids)
            ->with('detalles')
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get()
            ->unique('vehiculo_id')
            ->keyBy('vehiculo_id');

        $movimientos = MovimientoEquipamiento::query()
            ->vigentes()
            ->where(fn ($query) => $query->whereIn('vehiculo_id', $ids)->orWhereIn('vehiculo_destino_id', $ids))
            ->get();

        $delAlmacen = $this->delAlmacen($ids, $items);

        $vencimientos = VencimientoEquipamiento::query()
            ->whereIn('vehiculo_id', $ids)
            ->get()
            ->keyBy(fn (VencimientoEquipamiento $vencimiento): string => "{$vencimiento->vehiculo_id}-{$vencimiento->item_equipamiento_id}");

        $resultado = [];

        foreach ($vehiculos as $vehiculo) {
            $conteo = $ultimos->get($vehiculo->id);
            $filas = [];

            foreach ($items->filter(fn (ItemEquipamiento $item): bool => $item->aplica_a->incluye($vehiculo->tipo)) as $item) {
                $propios = $movimientos->where('item_equipamiento_id', $item->id);
                $salen = $propios->where('vehiculo_id', $vehiculo->id);
                $llegan = $propios->where('vehiculo_destino_id', $vehiculo->id)->where('tipo', TipoMovimientoEquipamiento::Traslado);
                $vales = $delAlmacen["{$vehiculo->id}-{$item->articulo_id}"] ?? ['cantidad' => 0, 'ultimo' => null];

                $asignado = max(0, (int) $salen->where('tipo', TipoMovimientoEquipamiento::SaldoInicial)->sum('cantidad')
                    + $vales['cantidad']
                    + (int) $llegan->sum('cantidad')
                    - (int) $salen->where('tipo', TipoMovimientoEquipamiento::Traslado)->sum('cantidad')
                    - (int) $salen->where('tipo', TipoMovimientoEquipamiento::Baja)->sum('cantidad'));

                $conHistorial = $salen->isNotEmpty() || $llegan->isNotEmpty() || $vales['ultimo'] !== null;
                $detalle = $conteo?->detalles->firstWhere('item_equipamiento_id', $item->id);
                $contado = $conteo !== null && $detalle !== null;

                $ultimaBaja = $salen->where('tipo', TipoMovimientoEquipamiento::Baja)->max('created_at');
                $ultimoCambio = collect([$salen->max('created_at'), $llegan->max('created_at'), $vales['ultimo']])->filter()->max();

                // Las dañadas que vio el último conteo siguen ahí hasta que
                // se den de baja; después de una baja ya no se sabe.
                $danadas = $contado && ($ultimaBaja === null || $conteo->created_at > $ultimaBaja)
                    ? min($detalle->danadas, $asignado)
                    : 0;
                $buenas = $asignado - $danadas;

                $vence = $item->lleva_vencimiento ? $vencimientos->get("{$vehiculo->id}-{$item->id}")?->vence_el : null;

                $filas[] = [
                    'id' => $item->id,
                    'nombre' => $item->nombre,
                    'articulo_id' => $item->articulo_id,
                    'lleva_vencimiento' => $item->lleva_vencimiento,
                    'minimo' => $item->minimo,
                    'asignado' => $asignado,
                    'buenas' => $conHistorial || $conteo !== null ? $buenas : null,
                    'danadas' => $conHistorial || $conteo !== null ? $danadas : null,
                    // Lo que el último conteo no encontró y nadie explicó.
                    'sin_explicar' => $contado && ($ultimoCambio === null || $conteo->created_at >= $ultimoCambio)
                        ? max(0, $asignado - $detalle->buenas - $detalle->danadas)
                        : 0,
                    'faltan' => $item->minimo === null || (! $conHistorial && $conteo === null) ? 0 : max(0, $item->minimo - $buenas),
                    'con_historial' => $conHistorial,
                    'vence_el' => $vence?->toDateString(),
                    'vencido' => $vence !== null && $asignado > 0 && $vence->lt($hoy),
                    'por_vencer' => $vence !== null && $asignado > 0 && $vence->gte($hoy) && $vence->lte($hoy->addDays(self::AVISO_DIAS)),
                ];
            }

            $faltan = 0;

            foreach ($filas as $fila) {
                $faltan += $fila['faltan'];
            }

            $vencidos = count(array_filter($filas, fn (array $fila): bool => $fila['vencido']));
            $sinNada = $conteo === null && array_filter($filas, fn (array $fila): bool => $fila['con_historial']) === [];

            $resultado[$vehiculo->id] = [
                'conteo' => $conteo === null ? null : ['id' => $conteo->id, 'fecha' => $conteo->fecha->toDateString()],
                'items' => $filas,
                'faltan' => $faltan,
                'vencidos' => $vencidos,
                'estado' => match (true) {
                    $sinNada => EstadoEquipamiento::SinContar,
                    $faltan > 0 || $vencidos > 0 => EstadoEquipamiento::Falta,
                    collect($filas)->whereNotNull('minimo')->isNotEmpty() => EstadoEquipamiento::Completo,
                    default => EstadoEquipamiento::Contado,
                },
            ];
        }

        return $resultado;
    }

    /**
     * Lo que salió del almacén para cada unidad, por artículo: lo de los
     * vales vigentes menos lo devuelto, y cuándo fue el último vale.
     *
     * @param  list<int>  $vehiculoIds
     * @param  Collection<int, ItemEquipamiento>  $items
     * @return array<string, array{cantidad: int, ultimo: CarbonInterface|null}>
     */
    private function delAlmacen(array $vehiculoIds, Collection $items): array
    {
        $articulos = $items->pluck('articulo_id')->filter()->values()->all();

        if ($articulos === []) {
            return [];
        }

        $lineas = DetalleSalida::query()
            ->whereIn('articulo_id', $articulos)
            ->whereIn('salida_almacen_id', SalidaAlmacen::query()->vigentes()->whereIn('vehiculo_id', $vehiculoIds)->select('id'))
            ->with(['salida', 'devoluciones.ingreso'])
            ->get();

        $resultado = [];

        foreach ($lineas as $linea) {
            $clave = "{$linea->salida->vehiculo_id}-{$linea->articulo_id}";
            $devuelto = (float) $linea->devoluciones
                ->filter(fn (DetalleIngreso $devolucion): bool => ! $devolucion->ingreso->estaAnulado())
                ->sum('cantidad_base');

            $previo = $resultado[$clave] ?? ['cantidad' => 0, 'ultimo' => null];

            $resultado[$clave] = [
                'cantidad' => $previo['cantidad'] + (int) floor((float) $linea->cantidad - $devuelto),
                'ultimo' => collect([$previo['ultimo'], $linea->salida->created_at])->filter()->max(),
            ];
        }

        return $resultado;
    }
}
