<?php

namespace App\Services;

use App\Enums\TipoIngreso;
use App\Enums\TipoMovimientoAlmacen;
use App\Models\Articulo;
use App\Models\DetalleIngreso;
use App\Models\DetalleInventario;
use App\Models\DetalleSalida;
use App\Models\IngresoAlmacen;
use App\Models\InventarioAlmacen;
use App\Models\MovimientoAlmacen;
use App\Models\SalidaAlmacen;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * El kardex valorizado del almacén, al costo promedio ponderado.
 *
 * Cada documento deja un movimiento por línea; los saldos no se calculan al
 * vuelo sino que se escriben en cada fila, recorriendo el historial del
 * artículo en orden de fecha y de registro:
 *
 * - una entrada suma su cantidad y su costo (una compra, el que dice el
 *   comprobante; una devolución, el mismo con que salió);
 * - una salida se lleva el costo promedio de ese momento, y si vacía el
 *   stock se lleva el valor que queda entero, para que no sobren céntimos.
 *
 * Recalcular el artículo entero, y no solo desde el movimiento nuevo, es lo
 * que deja registrar un comprobante con fecha atrasada o anular un documento
 * viejo sin que el saldo quede mal: las salidas posteriores se revalorizan
 * solas. Si con eso alguna salida queda sin stock, todo se rechaza.
 *
 * Se llama siempre dentro de la transacción del documento: si algo no
 * alcanza, la excepción deshace también el documento.
 */
class Kardex
{
    /** Por debajo de esto, una cantidad es cero: los decimales no se pierden en las restas. */
    private const TOLERANCIA = 0.0005;

    /**
     * Las líneas del ingreso entran al kardex.
     */
    public function registrarIngreso(IngresoAlmacen $ingreso, ?int $userId = null): void
    {
        $ingreso->loadMissing(['detalles', 'salida']);
        $tipo = $ingreso->tipo === TipoIngreso::Devolucion ? TipoMovimientoAlmacen::Devolucion : TipoMovimientoAlmacen::Compra;

        foreach ($ingreso->detalles as $detalle) {
            $this->crearMovimiento($detalle, [
                'articulo_id' => $detalle->articulo_id,
                'fecha' => $ingreso->fecha,
                'tipo' => $tipo,
                'documento' => $ingreso->documento(),
                'entrada' => $detalle->cantidad_base,
                'costo_unitario' => $detalle->costo_unitario,
                'costo_total' => $detalle->costo_total,
                'proveedor_id' => $ingreso->proveedor_id,
                'vehiculo_id' => $ingreso->salida?->vehiculo_id,
                'orden_trabajo_id' => $ingreso->salida?->orden_trabajo_id,
                'user_id' => $userId,
            ]);
        }

        $this->recalcular($this->articulosDe($ingreso->detalles));

        $ingreso->forceFill(['costo_total' => $ingreso->detalles()->sum('costo_total')])->saveQuietly();
    }

    /**
     * Las líneas del vale salen del kardex, al promedio del momento.
     */
    public function registrarSalida(SalidaAlmacen $salida, ?int $userId = null): void
    {
        $salida->loadMissing('detalles');

        foreach ($salida->detalles as $detalle) {
            $this->crearMovimiento($detalle, [
                'articulo_id' => $detalle->articulo_id,
                'fecha' => $salida->fecha,
                'tipo' => TipoMovimientoAlmacen::Salida,
                'documento' => $salida->numero(),
                'salida' => $detalle->cantidad,
                'vehiculo_id' => $salida->vehiculo_id,
                'orden_trabajo_id' => $salida->orden_trabajo_id,
                'user_id' => $userId,
            ]);
        }

        $this->recalcular($this->articulosDe($salida->detalles));
    }

    /**
     * Cierra el conteo: cada línea se compara con lo que el sistema dice que
     * había en esa fecha, y la diferencia entra (sobrante) o sale (faltante).
     * Para un artículo sin historial, lo contado es su saldo inicial.
     */
    public function registrarInventario(InventarioAlmacen $inventario, ?int $userId = null): void
    {
        $inventario->loadMissing('detalles');

        foreach ($inventario->detalles as $detalle) {
            $sistema = $this->stockAl($detalle->articulo_id, $inventario->fecha);
            $diferencia = round((float) $detalle->cantidad_contada - $sistema, 3);

            $detalle->update(['stock_sistema' => $sistema, 'diferencia' => $diferencia]);

            if (abs($diferencia) < self::TOLERANCIA) {
                continue;
            }

            if ($diferencia > 0) {
                $tieneHistorial = MovimientoAlmacen::query()->where('articulo_id', $detalle->articulo_id)->exists();
                $costo = $detalle->costo_unitario !== null
                    ? (float) $detalle->costo_unitario
                    : $this->costoAl($detalle->articulo_id, $inventario->fecha);

                $this->crearMovimiento($detalle, [
                    'articulo_id' => $detalle->articulo_id,
                    'fecha' => $inventario->fecha,
                    'tipo' => $tieneHistorial ? TipoMovimientoAlmacen::AjusteEntrada : TipoMovimientoAlmacen::SaldoInicial,
                    'documento' => $inventario->numero(),
                    'entrada' => $diferencia,
                    'costo_unitario' => round($costo, 4),
                    'costo_total' => round($diferencia * $costo, 2),
                    'user_id' => $userId,
                ]);

                continue;
            }

            $this->crearMovimiento($detalle, [
                'articulo_id' => $detalle->articulo_id,
                'fecha' => $inventario->fecha,
                'tipo' => TipoMovimientoAlmacen::AjusteSalida,
                'documento' => $inventario->numero(),
                'salida' => abs($diferencia),
                'user_id' => $userId,
            ]);
        }

        $this->recalcular($this->articulosDe($inventario->detalles));
    }

    /**
     * Saca del kardex los movimientos del documento y recalcula. Un ingreso
     * cuyo stock ya se despachó no se puede anular: primero sus salidas.
     */
    public function anular(IngresoAlmacen|SalidaAlmacen|InventarioAlmacen $documento): void
    {
        $detalles = $documento->detalles()->get();
        $tipoDetalle = $detalles->first()?->getMorphClass();

        if ($tipoDetalle !== null) {
            MovimientoAlmacen::query()
                ->where('origen_type', $tipoDetalle)
                ->whereIn('origen_id', $detalles->modelKeys())
                ->delete();
        }

        $this->recalcular($this->articulosDe($detalles));
    }

    /**
     * Rehace los saldos de los artículos desde su primer movimiento.
     *
     * @param  list<int>  $articuloIds
     *
     * @throws ValidationException si alguna salida queda sin stock
     */
    public function recalcular(array $articuloIds): void
    {
        $ids = array_values(array_unique($articuloIds));
        sort($ids);

        /** @var array<int, true> $salidasTocadas */
        $salidasTocadas = [];
        /** @var array<int, true> $ingresosTocados */
        $ingresosTocados = [];

        foreach ($ids as $id) {
            $articulo = Articulo::query()->lockForUpdate()->findOrFail($id);

            [$salidas, $ingresos] = $this->recalcularArticulo($articulo);

            $salidasTocadas += array_fill_keys($salidas, true);
            $ingresosTocados += array_fill_keys($ingresos, true);
        }

        // Los totales de los vales y de las devoluciones siguen a sus líneas.
        foreach (SalidaAlmacen::query()->whereIn('id', array_keys($salidasTocadas))->get() as $salida) {
            $salida->forceFill(['costo_total' => $salida->detalles()->sum('costo_total')])->saveQuietly();
        }

        foreach (IngresoAlmacen::query()->whereIn('id', array_keys($ingresosTocados))->get() as $ingreso) {
            $ingreso->forceFill(['costo_total' => $ingreso->detalles()->sum('costo_total')])->saveQuietly();
        }
    }

    /**
     * Cuánto había del artículo al terminar ese día.
     */
    public function stockAl(int $articuloId, CarbonInterface|string $fecha): float
    {
        $saldo = $this->ultimoAl($articuloId, $fecha);

        return $saldo === null ? 0.0 : (float) $saldo->saldo_cantidad;
    }

    /**
     * El costo promedio del artículo al terminar ese día.
     */
    public function costoAl(int $articuloId, CarbonInterface|string $fecha): float
    {
        $saldo = $this->ultimoAl($articuloId, $fecha);

        return $saldo === null ? 0.0 : (float) $saldo->saldo_costo_unitario;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function crearMovimiento(Model $detalle, array $datos): void
    {
        MovimientoAlmacen::query()->create([
            'origen_type' => $detalle->getMorphClass(),
            'origen_id' => $detalle->getKey(),
            'entrada' => 0,
            'salida' => 0,
            'costo_unitario' => 0,
            'costo_total' => 0,
            ...$datos,
        ]);
    }

    /**
     * Recorre el historial del artículo y escribe el saldo de cada fila.
     * Devuelve los vales y las devoluciones cuyas líneas cambiaron de costo.
     *
     * @return array{0: list<int>, 1: list<int>}
     */
    private function recalcularArticulo(Articulo $articulo): array
    {
        $movimientos = MovimientoAlmacen::query()
            ->where('articulo_id', $articulo->id)
            ->with('origen')
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();

        $cantidad = 0.0;
        $valor = 0.0;
        $salidas = [];
        $ingresos = [];
        /** @var array<int, float> $costoDeLinea el costo unitario con que salió cada línea de vale */
        $costoDeLinea = [];

        foreach ($movimientos as $movimiento) {
            if ($movimiento->tipo->esEntrada()) {
                $entrada = (float) $movimiento->entrada;

                if ($movimiento->tipo === TipoMovimientoAlmacen::Devolucion) {
                    // Vuelve al mismo costo con que salió.
                    $origen = $movimiento->origen;
                    $costoUnitario = $origen instanceof DetalleIngreso && $origen->detalle_salida_id !== null
                        ? ($costoDeLinea[$origen->detalle_salida_id] ?? (float) DetalleSalida::query()->whereKey($origen->detalle_salida_id)->value('costo_unitario'))
                        : (float) $movimiento->costo_unitario;
                    $costoTotal = round($entrada * $costoUnitario, 2);

                    if ($origen instanceof DetalleIngreso && $this->cambia($origen, $costoUnitario, $costoTotal)) {
                        $origen->forceFill(['costo_unitario' => round($costoUnitario, 4), 'costo_total' => $costoTotal])->saveQuietly();
                        $ingresos[] = $origen->ingreso_almacen_id;
                    }

                    $movimiento->costo_unitario = (string) round($costoUnitario, 4);
                    $movimiento->costo_total = (string) $costoTotal;
                }

                $cantidad = round($cantidad + $entrada, 3);
                $valor = round($valor + (float) $movimiento->costo_total, 2);
            } else {
                $sale = (float) $movimiento->salida;

                if ($sale - $cantidad > self::TOLERANCIA) {
                    $this->sinStock($articulo, $movimiento, $cantidad);
                }

                $costoUnitario = $cantidad > self::TOLERANCIA ? $valor / $cantidad : 0.0;
                $costoTotal = abs($sale - $cantidad) < self::TOLERANCIA ? $valor : round($sale * $costoUnitario, 2);

                $movimiento->costo_unitario = (string) round($costoUnitario, 4);
                $movimiento->costo_total = (string) $costoTotal;

                $origen = $movimiento->origen;

                if ($origen instanceof DetalleSalida) {
                    $costoDeLinea[$origen->id] = $costoUnitario;

                    if ($this->cambia($origen, $costoUnitario, $costoTotal)) {
                        $origen->forceFill(['costo_unitario' => round($costoUnitario, 4), 'costo_total' => $costoTotal])->saveQuietly();
                        $salidas[] = $origen->salida_almacen_id;
                    }
                } elseif ($origen instanceof DetalleInventario && $origen->costo_unitario === null) {
                    // El faltante se valoriza al promedio: queda anotado en la línea.
                    $origen->forceFill(['costo_unitario' => round($costoUnitario, 4)])->saveQuietly();
                }

                $cantidad = round($cantidad - $sale, 3);
                $valor = round($valor - $costoTotal, 2);
            }

            if (abs($cantidad) < self::TOLERANCIA) {
                $cantidad = 0.0;
                $valor = 0.0;
            }

            $movimiento->saldo_cantidad = (string) $cantidad;
            $movimiento->saldo_valor = (string) $valor;
            $movimiento->saldo_costo_unitario = (string) round($cantidad > 0 ? $valor / $cantidad : 0.0, 4);

            if ($movimiento->isDirty()) {
                $movimiento->saveQuietly();
            }
        }

        $ultimo = $movimientos->last();

        $articulo->forceFill([
            'stock' => $cantidad,
            'valor' => $valor,
            'costo_promedio' => $cantidad > 0
                ? round($valor / $cantidad, 4)
                // Sin stock se queda con el último costo conocido: sirve de
                // referencia para cotizar y para el próximo conteo.
                : ($ultimo instanceof MovimientoAlmacen ? $ultimo->costo_unitario : $articulo->costo_promedio),
        ])->saveQuietly();

        return [array_values(array_unique($salidas)), array_values(array_unique($ingresos))];
    }

    /**
     * Los artículos que tocan las líneas de un documento.
     *
     * @param  iterable<DetalleIngreso|DetalleSalida|DetalleInventario>  $detalles
     * @return list<int>
     */
    private function articulosDe(iterable $detalles): array
    {
        $ids = [];

        foreach ($detalles as $detalle) {
            $ids[] = $detalle->articulo_id;
        }

        return $ids;
    }

    private function cambia(DetalleSalida|DetalleIngreso $linea, float $costoUnitario, float $costoTotal): bool
    {
        return abs((float) $linea->costo_unitario - round($costoUnitario, 4)) > 0.00005
            || abs((float) $linea->costo_total - $costoTotal) > 0.005;
    }

    private function ultimoAl(int $articuloId, CarbonInterface|string $fecha): ?MovimientoAlmacen
    {
        return MovimientoAlmacen::query()
            ->where('articulo_id', $articuloId)
            ->whereDate('fecha', '<=', Carbon::parse($fecha)->toDateString())
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @throws ValidationException
     */
    private function sinStock(Articulo $articulo, MovimientoAlmacen $movimiento, float $habia): never
    {
        $unidad = $articulo->unidad->abreviatura();
        $fecha = $movimiento->fecha->format('d/m/Y');

        throw ValidationException::withMessages([
            'stock' => "No alcanza el stock de {$articulo->nombre}: el {$fecha} había "
                .$this->cifra($habia)." {$unidad} y {$movimiento->documento} saca "
                .$this->cifra((float) $movimiento->salida)." {$unidad}.",
        ]);
    }

    private function cifra(float $cantidad): string
    {
        return rtrim(rtrim(number_format($cantidad, 3, '.', ','), '0'), '.');
    }
}
