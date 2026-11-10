<?php

namespace App\Http\Controllers\Almacen\Concerns;

use App\Enums\EstadoOrdenTrabajo;
use App\Models\Articulo;
use App\Models\OrdenTrabajo;
use App\Models\Proveedor;
use App\Models\User;
use App\Models\Vehiculo;

/**
 * Las listas para elegir en los formularios del almacén y la regla de los
 * costos: quien no tiene `almacen.ver_costos` recibe las cifras de plata en
 * null, no escondidas en la pantalla.
 */
trait CatalogosAlmacen
{
    protected function veCostos(?User $user): bool
    {
        return $user?->can('verCostos', Articulo::class) ?? false;
    }

    /**
     * Una cifra de plata, o null si no la puede ver.
     */
    protected function costo(bool $veCostos, int|float|string|null $monto): ?float
    {
        return $veCostos && $monto !== null ? (float) $monto : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function articulosParaElegir(bool $veCostos, bool $soloConStock = false): array
    {
        return array_values(Articulo::query()
            ->withExists('movimientos as con_historial')
            // Para despachar vale todo lo que tenga stock, aunque esté dado de
            // baja: es la forma de vaciarlo. Para comprar, solo los activos.
            ->when(
                $soloConStock,
                fn ($query) => $query->where('stock', '>', 0),
                fn ($query) => $query->where('activo', true),
            )
            ->orderBy('nombre')
            ->get()
            ->map(fn (Articulo $articulo): array => [
                'id' => $articulo->id,
                'codigo' => $articulo->codigo,
                'nombre' => $articulo->activo ? $articulo->nombre : "{$articulo->nombre} (dado de baja)",
                'numero_parte' => $articulo->numero_parte,
                'marca' => $articulo->marca,
                'unidad' => $articulo->unidad->abreviatura(),
                'unidad_compra' => $articulo->unidad_compra?->abreviatura(),
                'factor_compra' => $articulo->factor(),
                'ubicacion_id' => $articulo->ubicacion_almacen_id,
                'con_historial' => (bool) $articulo->getAttribute('con_historial'),
                'stock' => (float) $articulo->stock,
                'costo_promedio' => $this->costo($veCostos, $articulo->costo_promedio),
            ])
            ->all());
    }

    /**
     * @return list<array{id: int, placa: string, tipo: string, marca: string|null}>
     */
    protected function vehiculosParaElegir(): array
    {
        return array_values(Vehiculo::query()
            ->orderBy('tipo')
            ->orderBy('placa')
            ->get(['id', 'placa', 'tipo', 'marca'])
            ->map(fn (Vehiculo $vehiculo): array => [
                'id' => $vehiculo->id,
                'placa' => $vehiculo->placa,
                'tipo' => $vehiculo->tipo->label(),
                'marca' => $vehiculo->marca,
            ])
            ->all());
    }

    /**
     * Las órdenes a las que se les puede cargar repuestos: las abiertas. A
     * una finalizada o anulada ya no le sale nada; si faltó algo, se reabre.
     *
     * @return list<array{id: int, etiqueta: string, vehiculo_id: int, placa: string}>
     */
    protected function ordenesParaElegir(): array
    {
        return array_values(OrdenTrabajo::query()
            ->with('vehiculo:id,placa')
            ->vigentes()
            ->where('estado', '!=', EstadoOrdenTrabajo::Finalizada)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn (OrdenTrabajo $orden): array => [
                'id' => $orden->id,
                'etiqueta' => sprintf('%s · %s · %s', $orden->numero(), $orden->vehiculo->placa, mb_strimwidth($orden->trabajo, 0, 60, '…')),
                'vehiculo_id' => $orden->vehiculo_id,
                'placa' => $orden->vehiculo->placa,
            ])
            ->all());
    }

    /**
     * @return list<array{id: int, nombre: string, razon_social: string, ruc: string|null}>
     */
    protected function proveedoresParaElegir(): array
    {
        return array_values(Proveedor::query()
            ->where('activo', true)
            ->orderBy('razon_social')
            ->get()
            ->map(fn (Proveedor $proveedor): array => [
                'id' => $proveedor->id,
                'nombre' => $proveedor->nombre(),
                'razon_social' => $proveedor->razon_social,
                'ruc' => $proveedor->ruc,
            ])
            ->all());
    }
}
