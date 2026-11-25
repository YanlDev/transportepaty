<?php

namespace App\Services;

use App\Enums\EstadoValeCompra;
use App\Enums\SituacionValeCompra;
use App\Models\DetalleIngreso;
use App\Models\DetalleSalida;
use App\Models\ReposicionEquipamiento;
use App\Models\SalidaAlmacen;
use App\Models\ValeCompra;
use App\Models\Vehiculo;

/**
 * El puente entre lo que taller pide reponer del equipamiento de una unidad
 * y el almacén: qué falta entregarle, de qué hay stock y qué hay que comprar.
 *
 * Nada se guarda como atendido: de un pedido falta lo que no salió todavía
 * en vales a nombre de esa unidad hechos después de pedirlo.
 *
 * @phpstan-type LineaReposicion array{reposicion_id: int, item_equipamiento_id: int, articulo_id: int|null, descripcion: string, nota: string|null, pedido: int, pendiente: int, hay: int, en_compra: string|null}
 * @phpstan-type UnidadPorReponer array{vehiculo_id: int, placa: string, pedido_por: string|null, lineas: list<LineaReposicion>, se_puede: bool, por_comprar: bool}
 */
class ReposicionesDeEquipamiento
{
    /**
     * Los pedidos vigentes que todavía esperan algo, del más antiguo al más
     * nuevo, cada uno con lo que le falta recibir.
     *
     * @return list<array{reposicion: ReposicionEquipamiento, pendiente: int}>
     */
    public function pendientes(?Vehiculo $vehiculo = null): array
    {
        $pedidos = ReposicionEquipamiento::query()
            ->vigentes()
            ->when($vehiculo !== null, fn ($query) => $query->where('vehiculo_id', $vehiculo?->id))
            ->with(['item.articulo:id,stock', 'vehiculo:id,placa', 'user:id,name'])
            ->orderBy('id')
            ->get();

        if ($pedidos->isEmpty()) {
            return [];
        }

        $salidas = DetalleSalida::query()
            ->whereIn('articulo_id', $pedidos->pluck('item.articulo_id')->filter()->unique()->values())
            ->whereIn('salida_almacen_id', SalidaAlmacen::query()->vigentes()->whereIn('vehiculo_id', $pedidos->pluck('vehiculo_id')->unique()->values())->select('id'))
            ->with(['salida:id,vehiculo_id,created_at', 'devoluciones.ingreso'])
            ->get();

        $pendientes = [];

        foreach ($pedidos as $pedido) {
            $entregado = $salidas
                ->filter(fn (DetalleSalida $linea): bool => $linea->articulo_id === $pedido->item->articulo_id
                    && $linea->salida->vehiculo_id === $pedido->vehiculo_id
                    && $linea->salida->created_at >= $pedido->created_at)
                ->sum(fn (DetalleSalida $linea): float => (float) $linea->cantidad - (float) $linea->devoluciones
                    ->filter(fn (DetalleIngreso $devolucion): bool => ! $devolucion->ingreso->estaAnulado())
                    ->sum('cantidad_base'));

            $pendiente = $pedido->cantidad - (int) floor($entregado);

            if ($pendiente > 0) {
                $pendientes[] = ['reposicion' => $pedido, 'pendiente' => $pendiente];
            }
        }

        return $pendientes;
    }

    /**
     * Lo que el almacén tiene que reponer, unidad por unidad: qué pide cada
     * una, de qué hay y de qué no. El stock se reparte en el orden en que se
     * pidió: lo que cubre a una unidad no cubre a la siguiente.
     *
     * @return list<UnidadPorReponer>
     */
    public function porAtender(?Vehiculo $vehiculo = null): array
    {
        $pendientes = $this->pendientes($vehiculo);
        $articuloIds = [];

        foreach ($pendientes as ['reposicion' => $pedido]) {
            if ($pedido->item->articulo_id !== null) {
                $articuloIds[$pedido->item->articulo_id] = $pedido->item->articulo_id;
            }
        }

        $enCompra = $this->enCompra(array_values($articuloIds));
        $stock = [];
        $unidades = [];

        foreach ($pendientes as ['reposicion' => $pedido, 'pendiente' => $pendiente]) {
            $articulo = $pedido->item->articulo;
            $hay = 0;

            if ($articulo !== null) {
                $stock[$articulo->id] ??= max(0, (int) floor((float) $articulo->stock));
                $hay = min($pendiente, $stock[$articulo->id]);
                $stock[$articulo->id] -= $hay;
            }

            $unidades[$pedido->vehiculo_id] ??= [
                'vehiculo_id' => $pedido->vehiculo_id,
                'placa' => $pedido->vehiculo->placa,
                'pedido_por' => $pedido->user?->name,
                'lineas' => [],
                'se_puede' => false,
                'por_comprar' => false,
            ];

            $unidades[$pedido->vehiculo_id]['lineas'][] = [
                'reposicion_id' => $pedido->id,
                'item_equipamiento_id' => $pedido->item_equipamiento_id,
                'articulo_id' => $articulo?->id,
                'descripcion' => $pedido->item->nombre,
                'nota' => $pedido->nota,
                'pedido' => $pedido->cantidad,
                'pendiente' => $pendiente,
                'hay' => $hay,
                'en_compra' => $articulo === null ? null : ($enCompra[$articulo->id] ?? null),
            ];

            $unidades[$pedido->vehiculo_id]['se_puede'] = $unidades[$pedido->vehiculo_id]['se_puede'] || $hay > 0;
            $unidades[$pedido->vehiculo_id]['por_comprar'] = $unidades[$pedido->vehiculo_id]['por_comprar']
                || ($hay < $pendiente && $articulo !== null && ! isset($enCompra[$articulo->id]));
        }

        return array_values($unidades);
    }

    /**
     * Las líneas con que nace el vale de salida a la unidad: lo pedido de lo
     * que hay stock.
     *
     * @return list<array{articulo_id: int, cantidad: float}>
     */
    public function entregables(Vehiculo $vehiculo): array
    {
        return $this->lineas($vehiculo, fn (array $linea): int => $linea['hay']);
    }

    /**
     * Las líneas con que nace el pedido de compra: lo que el stock no cubre.
     *
     * @return list<array{articulo_id: int, cantidad: float}>
     */
    public function porComprar(Vehiculo $vehiculo): array
    {
        return $this->lineas($vehiculo, fn (array $linea): int => $linea['pendiente'] - $linea['hay']);
    }

    /**
     * @param  callable(LineaReposicion): int  $cantidad
     * @return list<array{articulo_id: int, cantidad: float}>
     */
    private function lineas(Vehiculo $vehiculo, callable $cantidad): array
    {
        $porArticulo = [];

        // Se reparte el stock entre todas las unidades y se toma lo de esta.
        foreach ($this->porAtender() as $unidad) {
            if ($unidad['vehiculo_id'] !== $vehiculo->id) {
                continue;
            }

            foreach ($unidad['lineas'] as $linea) {
                if ($linea['articulo_id'] !== null && $cantidad($linea) > 0) {
                    $porArticulo[$linea['articulo_id']] = ($porArticulo[$linea['articulo_id']] ?? 0) + $cantidad($linea);
                }
            }
        }

        return array_map(
            fn (int $articuloId, int $unidades): array => ['articulo_id' => $articuloId, 'cantidad' => (float) $unidades],
            array_keys($porArticulo),
            array_values($porArticulo),
        );
    }

    /**
     * Los artículos que ya tienen un pedido de compra en camino (por aprobar
     * o por recibir), con su número: para no pedirlos dos veces.
     *
     * @param  list<int>  $articuloIds
     * @return array<int, string>
     */
    private function enCompra(array $articuloIds): array
    {
        if ($articuloIds === []) {
            return [];
        }

        $enCamino = [];

        $vales = ValeCompra::query()
            ->vigentes()
            ->whereIn('estado', [EstadoValeCompra::Solicitado, EstadoValeCompra::Aprobado])
            ->whereHas('detalles', fn ($detalles) => $detalles->whereIn('articulo_id', $articuloIds))
            ->with('detalles')
            ->orderBy('id')
            ->get();

        foreach ($vales as $vale) {
            if (! in_array($vale->situacion(), [SituacionValeCompra::PorAprobar, SituacionValeCompra::Vigente, SituacionValeCompra::Parcial], true)) {
                continue;
            }

            foreach ($vale->detalles as $detalle) {
                if ($detalle->articulo_id !== null && in_array($detalle->articulo_id, $articuloIds, true)) {
                    $enCamino[$detalle->articulo_id] ??= $vale->numero();
                }
            }
        }

        return $enCamino;
    }
}
