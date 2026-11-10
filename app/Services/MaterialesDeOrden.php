<?php

namespace App\Services;

use App\Enums\EstadoValeCompra;
use App\Models\Articulo;
use App\Models\DetalleIngreso;
use App\Models\DetalleSalida;
use App\Models\DetalleValeCompra;
use App\Models\IngresoAlmacen;
use App\Models\OrdenTrabajo;
use App\Models\RepuestoServicio;
use App\Models\SalidaAlmacen;
use App\Models\ServicioMantenimiento;
use App\Models\ValeCompra;
use App\Models\Vehiculo;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lo que una orden de trabajo consumió del almacén, y lo que le falta.
 *
 * La orden no guarda materiales propios: sus materiales son las líneas de los
 * vales de salida vigentes que la nombran, menos lo que se devolvió. De ahí
 * sale también su costo. Así no hay forma de anotar un repuesto en una orden
 * sin que haya salido del almacén.
 *
 * Para una preventiva, lo que debe salir es lo que pide su nivel y los que
 * contiene (la S incluye la X). Un ítem marcado como servicio no sale del
 * almacén; uno sin artículo ligado no se puede comprobar y cuenta como
 * faltante hasta que se ligue en el plan.
 *
 * @phpstan-type Requerido array{
 *     servicio: string,
 *     descripcion: string,
 *     cantidad: float|null,
 *     unidad: string|null,
 *     es_servicio: bool,
 *     articulo_id: int|null,
 *     articulo: string|null,
 *     stock: float|null,
 *     despachado: float,
 *     falta: bool,
 *     falta_stock: bool,
 * }
 * @phpstan-type Linea array{
 *     id: int,
 *     salida_id: int,
 *     vale: string,
 *     fecha: string,
 *     orden_trabajo_id: int|null,
 *     articulo_id: int,
 *     codigo: string,
 *     nombre: string,
 *     unidad: string,
 *     cantidad: float,
 *     devuelto: float,
 *     costo: float,
 * }
 */
class MaterialesDeOrden
{
    private const TOLERANCIA = 0.0005;

    /**
     * Cada ítem que pide el servicio (con los niveles que contiene), frente
     * a lo que ya salió para la orden y a lo que hay en stock.
     *
     * @return list<Requerido>
     */
    public function requeridos(ServicioMantenimiento $servicio, ?OrdenTrabajo $orden = null): array
    {
        $niveles = ServicioMantenimiento::query()
            ->where('plan_mantenimiento_id', $servicio->plan_mantenimiento_id)
            ->where('nivel', '<=', $servicio->nivel)
            ->with('repuestos.articulo')
            ->orderBy('nivel')
            ->get();

        // Si dos ítems piden el mismo artículo, lo despachado y el stock se
        // reparten en orden: lo que cubre al primero no cubre al segundo.
        $despachado = $orden === null ? [] : $this->despachado($orden);
        $stock = [];
        $requeridos = [];

        foreach ($niveles as $nivel) {
            foreach ($nivel->repuestos as $repuesto) {
                $requeridos[] = $this->requerido($nivel->codigo, $repuesto, $despachado, $stock);
            }
        }

        return $requeridos;
    }

    /**
     * Lo que la preventiva pide y todavía no salió del almacén para la orden.
     *
     * @return list<string>
     */
    public function faltantes(ServicioMantenimiento $servicio, ?OrdenTrabajo $orden = null): array
    {
        return array_values(array_map(
            fn (array $requerido): string => $requerido['articulo'] === null
                ? "{$requerido['descripcion']} (sin artículo de almacén ligado)"
                : $requerido['descripcion'],
            array_filter($this->requeridos($servicio, $orden), fn (array $requerido): bool => $requerido['falta']),
        ));
    }

    /**
     * Cuántos ítems del servicio no alcanzan hoy en el stock.
     */
    public function faltanEnStock(ServicioMantenimiento $servicio): int
    {
        return count(array_filter($this->requeridos($servicio), fn (array $requerido): bool => $requerido['falta_stock']));
    }

    /**
     * Lo que salió para la orden, neto de devoluciones, por artículo.
     *
     * @return array<int, float>
     */
    public function despachado(OrdenTrabajo $orden): array
    {
        $neto = [];

        foreach ($this->lineasDe(SalidaAlmacen::query()->where('orden_trabajo_id', $orden->id)) as $linea) {
            $neto[$linea['articulo_id']] = round(($neto[$linea['articulo_id']] ?? 0.0) + $linea['cantidad'] - $linea['devuelto'], 3);
        }

        return $neto;
    }

    /**
     * Las líneas de los vales vigentes de la orden, de la más reciente.
     *
     * @return list<Linea>
     */
    public function lineas(OrdenTrabajo $orden): array
    {
        return $this->lineasDe(SalidaAlmacen::query()->where('orden_trabajo_id', $orden->id));
    }

    /**
     * Todo lo que se le puso a la unidad, con orden o sin ella.
     *
     * @return list<Linea>
     */
    public function lineasDeUnidad(Vehiculo $vehiculo, int $limite = 100): array
    {
        return $this->lineasDe(SalidaAlmacen::query()->where('vehiculo_id', $vehiculo->id), $limite);
    }

    /**
     * El costo de cada orden: lo que salió del almacén menos lo que volvió,
     * más los servicios de terceros ya atendidos.
     *
     * @param  list<int>  $ordenIds
     * @return array<int, float>
     */
    public function costos(array $ordenIds): array
    {
        $costos = array_fill_keys($ordenIds, 0.0);

        foreach ($this->lineasDe(SalidaAlmacen::query()->whereIn('orden_trabajo_id', $ordenIds)) as $linea) {
            $costos[(int) $linea['orden_trabajo_id']] = round($costos[(int) $linea['orden_trabajo_id']] + $linea['costo'], 2);
        }

        foreach ($this->tercerosDe($ordenIds) as $servicio) {
            $costos[$servicio['orden_trabajo_id']] = round($costos[$servicio['orden_trabajo_id']] + $servicio['total'], 2);
        }

        return $costos;
    }

    /**
     * Los servicios de terceros de las órdenes: líneas de servicio de sus
     * vales de compra aprobados. Cuentan como costo una vez atendidos.
     *
     * @param  list<int>  $ordenIds
     * @return list<array{id: int, orden_trabajo_id: int, vale_id: int, vale: string, descripcion: string, proveedor: string, total: float, atendido: bool}>
     */
    public function terceros(array $ordenIds): array
    {
        return array_values(DetalleValeCompra::query()
            ->where('es_servicio', true)
            ->whereIn('vale_compra_id', ValeCompra::query()
                ->vigentes()
                ->where('estado', EstadoValeCompra::Aprobado)
                ->whereIn('orden_trabajo_id', $ordenIds)
                ->select('id'))
            ->with('vale.proveedor')
            ->orderBy('id')
            ->get()
            ->map(fn (DetalleValeCompra $detalle): array => [
                'id' => $detalle->id,
                'orden_trabajo_id' => (int) $detalle->vale->orden_trabajo_id,
                'vale_id' => $detalle->vale_compra_id,
                'vale' => $detalle->vale->numero(),
                'descripcion' => $detalle->descripcion,
                'proveedor' => $detalle->vale->proveedor->nombre(),
                'total' => (float) $detalle->total,
                'atendido' => $detalle->atendido_at !== null,
            ])
            ->all());
    }

    /**
     * @param  list<int>  $ordenIds
     * @return list<array{orden_trabajo_id: int, total: float}>
     */
    private function tercerosDe(array $ordenIds): array
    {
        return array_values(array_filter($this->terceros($ordenIds), fn (array $servicio): bool => $servicio['atendido']));
    }

    public function costo(OrdenTrabajo $orden): float
    {
        return $this->costos([$orden->id])[$orden->id];
    }

    /**
     * Lo gastado del almacén en la unidad, en toda su historia.
     */
    public function costoDeUnidad(Vehiculo $vehiculo): float
    {
        $lineas = DetalleSalida::query()->whereIn(
            'salida_almacen_id',
            SalidaAlmacen::query()->vigentes()->where('vehiculo_id', $vehiculo->id)->select('id'),
        );

        $salio = (float) (clone $lineas)->sum('costo_total');

        $volvio = (float) DetalleIngreso::query()
            ->whereIn('detalle_salida_id', $lineas->select('id'))
            ->whereIn('ingreso_almacen_id', IngresoAlmacen::query()->vigentes()->select('id'))
            ->sum('costo_total');

        return round($salio - $volvio, 2);
    }

    /**
     * @param  array<int, float>  $despachado  lo que queda por repartir de cada artículo
     * @param  array<int, float>  $stock  ídem, del stock
     * @return Requerido
     */
    private function requerido(string $codigoNivel, RepuestoServicio $repuesto, array &$despachado, array &$stock): array
    {
        $articulo = $repuesto->articulo;
        $pide = $repuesto->cantidad === null ? 0.0 : (float) $repuesto->cantidad;
        $base = [
            'servicio' => $codigoNivel,
            'descripcion' => $repuesto->descripcion,
            'cantidad' => $repuesto->cantidad === null ? null : $pide,
            'unidad' => $articulo instanceof Articulo ? $articulo->unidad->abreviatura() : $repuesto->unidad,
            'es_servicio' => $repuesto->es_servicio,
            'articulo_id' => $articulo?->id,
            'articulo' => $articulo?->nombre,
            'stock' => $articulo === null ? null : (float) $articulo->stock,
        ];

        if ($repuesto->es_servicio) {
            return [...$base, 'despachado' => 0.0, 'falta' => false, 'falta_stock' => false];
        }

        if (! $articulo instanceof Articulo) {
            return [...$base, 'despachado' => 0.0, 'falta' => true, 'falta_stock' => true];
        }

        $stock[$articulo->id] ??= (float) $articulo->stock;

        $salio = min($pide, $despachado[$articulo->id] ?? 0.0);
        $despachado[$articulo->id] = ($despachado[$articulo->id] ?? 0.0) - $salio;

        $hay = min($pide, $stock[$articulo->id]);
        $stock[$articulo->id] -= $hay;

        return [
            ...$base,
            'despachado' => round($salio, 3),
            // Sin cantidad en el plan basta con que haya salido algo.
            'falta' => $pide > 0 ? $pide - $salio > self::TOLERANCIA : $despachado[$articulo->id] <= self::TOLERANCIA,
            'falta_stock' => $pide > 0 ? $pide - $hay > self::TOLERANCIA : $stock[$articulo->id] <= self::TOLERANCIA,
        ];
    }

    /**
     * @param  Builder<SalidaAlmacen>  $salidas  los vales de los que se quieren las líneas
     * @return list<Linea>
     */
    private function lineasDe(Builder $salidas, ?int $limite = null): array
    {
        $detalles = DetalleSalida::query()
            ->whereIn('salida_almacen_id', $salidas->vigentes()->select('id'))
            ->with(['salida', 'articulo', 'devoluciones.ingreso'])
            ->orderByDesc('id')
            ->when($limite !== null, fn (Builder $query) => $query->limit((int) $limite))
            ->get();

        return array_values($detalles->map(function (DetalleSalida $detalle): array {
            $vuelto = $detalle->devoluciones->filter(fn (DetalleIngreso $devolucion): bool => ! $devolucion->ingreso->estaAnulado());

            return [
                'id' => $detalle->id,
                'salida_id' => $detalle->salida_almacen_id,
                'vale' => $detalle->salida->numero(),
                'fecha' => $detalle->salida->fecha->toDateString(),
                'orden_trabajo_id' => $detalle->salida->orden_trabajo_id,
                'articulo_id' => $detalle->articulo_id,
                'codigo' => $detalle->articulo->codigo,
                'nombre' => $detalle->articulo->nombre,
                'unidad' => $detalle->articulo->unidad->abreviatura(),
                'cantidad' => (float) $detalle->cantidad,
                'devuelto' => round((float) $vuelto->sum('cantidad_base'), 3),
                'costo' => round((float) $detalle->costo_total - (float) $vuelto->sum('costo_total'), 2),
            ];
        })->all());
    }
}
