<?php

namespace App\Services;

use App\Enums\EstadoOrdenTrabajo;
use App\Enums\UnidadMedida;
use App\Models\Articulo;
use App\Models\OrdenTrabajo;
use App\Models\RepuestoOrden;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * El puente entre lo que el mecánico pidió para una orden y el almacén: qué
 * falta entregarle a cada orden, qué stock queda reservado para ellas y qué
 * hay que comprar.
 *
 * Nada de esto se guarda: lo pedido son las líneas aprobadas de
 * `repuestos_orden`, lo entregado son las entregas a nombre de la
 * orden, y lo reservado es la diferencia mientras la orden siga abierta.
 *
 * @phpstan-type Pendiente array{repuesto_id: int, articulo_id: int|null, descripcion: string, unidad: string|null, pedido: float, entregado: float, pendiente: float, stock: float|null}
 */
class RepuestosDeOrden
{
    private const TOLERANCIA = 0.0005;

    public function __construct(private readonly MaterialesDeOrden $materiales) {}

    /**
     * Las líneas aprobadas de la orden con lo que ya salió y lo que falta.
     * Lo entregado se reparte en orden entre las líneas del mismo artículo;
     * una línea en texto no se puede cruzar con los vales: sigue pendiente
     * hasta que se pase al catálogo.
     *
     * @return list<Pendiente>
     */
    public function pendientes(OrdenTrabajo $orden): array
    {
        $entregado = $this->materiales->despachado($orden);
        $lineas = [];

        foreach ($orden->repuestos()->whereNotNull('aprobado_at')->with('articulo:id,stock,unidad')->get() as $repuesto) {
            $pedido = (float) $repuesto->cantidad;
            $recibido = 0.0;

            if ($repuesto->articulo_id !== null) {
                $recibido = min($pedido, $entregado[$repuesto->articulo_id] ?? 0.0);
                $entregado[$repuesto->articulo_id] = ($entregado[$repuesto->articulo_id] ?? 0.0) - $recibido;
            }

            $lineas[] = [
                'repuesto_id' => $repuesto->id,
                'articulo_id' => $repuesto->articulo_id,
                'descripcion' => $repuesto->descripcion,
                'unidad' => $repuesto->unidad,
                'pedido' => $pedido,
                'entregado' => round($recibido, 3),
                'pendiente' => round(max($pedido - $recibido, 0), 3),
                'stock' => $repuesto->articulo === null ? null : (float) $repuesto->articulo->stock,
            ];
        }

        return $lineas;
    }

    /**
     * Lo que falta entregarle a la orden, por artículo del catálogo.
     *
     * @return array<int, float>
     */
    public function pendientePorArticulo(OrdenTrabajo $orden): array
    {
        $porArticulo = [];

        foreach ($this->pendientes($orden) as $linea) {
            if ($linea['articulo_id'] !== null && $linea['pendiente'] > self::TOLERANCIA) {
                $porArticulo[$linea['articulo_id']] = round(($porArticulo[$linea['articulo_id']] ?? 0.0) + $linea['pendiente'], 3);
            }
        }

        return $porArticulo;
    }

    /**
     * Las órdenes abiertas y aprobadas que tienen repuestos pedidos.
     *
     * @return Collection<int, OrdenTrabajo>
     */
    public function ordenesConPedido(): Collection
    {
        return OrdenTrabajo::query()
            ->enTaller()
            ->whereNotIn('estado', [EstadoOrdenTrabajo::Solicitada, EstadoOrdenTrabajo::Requerimiento])
            ->whereHas('repuestos', fn (Builder $repuestos) => $repuestos->whereNotNull('aprobado_at'))
            ->with(['vehiculo:id,placa', 'mecanicos:id,name'])
            ->orderByRaw("case prioridad when 'urgente' then 0 else 1 end")
            ->orderBy('id')
            ->get();
    }

    /**
     * El stock apartado para las órdenes abiertas: lo aprobado que todavía
     * no se les entregó. `$menos` deja fuera a una orden (la que se está
     * atendiendo: su propia reserva no le estorba).
     *
     * @return array<int, array{cantidad: float, ordenes: list<string>}>
     */
    public function reservado(?int $menos = null): array
    {
        $reservado = [];

        foreach ($this->ordenesConPedido() as $orden) {
            if ($orden->id === $menos) {
                continue;
            }

            foreach ($this->pendientePorArticulo($orden) as $articuloId => $cantidad) {
                $reservado[$articuloId]['cantidad'] = round(($reservado[$articuloId]['cantidad'] ?? 0.0) + $cantidad, 3);
                $reservado[$articuloId]['ordenes'][] = $orden->numero();
            }
        }

        return $reservado;
    }

    /**
     * Lo que el almacén tiene que entregar: cada orden con sus líneas
     * pendientes y lo que hay para darle hoy.
     *
     * @return list<array{id: int, numero: string, placa: string, urgente: bool, mecanico: string|null, lineas: list<array{descripcion: string, unidad: string|null, pendiente: float, hay: float}>, se_puede: bool}>
     */
    public function porEntregar(): array
    {
        $entregas = [];

        foreach ($this->ordenesConPedido() as $orden) {
            $lineas = [];

            foreach ($this->pendientes($orden) as $linea) {
                if ($linea['articulo_id'] === null || $linea['pendiente'] <= self::TOLERANCIA) {
                    continue;
                }

                $lineas[] = [
                    'descripcion' => $linea['descripcion'],
                    'unidad' => $linea['unidad'],
                    'pendiente' => $linea['pendiente'],
                    'hay' => min($linea['pendiente'], max((float) $linea['stock'], 0)),
                ];
            }

            if ($lineas === []) {
                continue;
            }

            $entregas[] = [
                'id' => $orden->id,
                'numero' => $orden->numero(),
                'placa' => $orden->vehiculo->placa,
                'urgente' => $orden->prioridad->value === 'urgente',
                'mecanico' => $this->responsable($orden)?->name,
                'lineas' => $lineas,
                // Con que haya algo de alguna línea, ya se puede entregar.
                'se_puede' => array_sum(array_column($lineas, 'hay')) > self::TOLERANCIA,
            ];
        }

        return $entregas;
    }

    /**
     * Lo que hay que comprar para las órdenes: lo pedido en texto (no está
     * en el catálogo) y lo del catálogo que el stock no cubre.
     *
     * @return list<array{id: int, numero: string, placa: string, urgente: bool, lineas: list<array{repuesto_id: int, descripcion: string, unidad: string|null, falta: float, en_catalogo: bool}>}>
     */
    public function porComprar(): array
    {
        $compras = [];
        $stock = [];

        foreach ($this->ordenesConPedido() as $orden) {
            $lineas = [];

            foreach ($this->pendientes($orden) as $linea) {
                if ($linea['pendiente'] <= self::TOLERANCIA) {
                    continue;
                }

                $falta = $linea['pendiente'];

                // El stock se reparte entre las órdenes en el orden en que
                // se atienden: lo que cubre a una no cubre a la siguiente.
                if ($linea['articulo_id'] !== null) {
                    $stock[$linea['articulo_id']] ??= max((float) $linea['stock'], 0);
                    $cubre = min($falta, $stock[$linea['articulo_id']]);
                    $stock[$linea['articulo_id']] -= $cubre;
                    $falta = round($falta - $cubre, 3);
                }

                if ($falta > self::TOLERANCIA) {
                    $lineas[] = [
                        'repuesto_id' => $linea['repuesto_id'],
                        'descripcion' => $linea['descripcion'],
                        'unidad' => $linea['unidad'],
                        'falta' => $falta,
                        'en_catalogo' => $linea['articulo_id'] !== null,
                    ];
                }
            }

            if ($lineas !== []) {
                $compras[] = [
                    'id' => $orden->id,
                    'numero' => $orden->numero(),
                    'placa' => $orden->vehiculo->placa,
                    'urgente' => $orden->prioridad->value === 'urgente',
                    'lineas' => $lineas,
                ];
            }
        }

        return $compras;
    }

    /**
     * Lo del catálogo que a la orden le falta y el stock no cubre: las
     * líneas con que nace su pedido de compra.
     *
     * @return list<array{articulo_id: int, cantidad: float}>
     */
    public function faltanteParaComprar(OrdenTrabajo $orden): array
    {
        $faltante = [];

        foreach ($this->pendientes($orden) as $linea) {
            if ($linea['articulo_id'] === null) {
                continue;
            }

            $falta = round($linea['pendiente'] - max((float) $linea['stock'], 0), 3);

            if ($falta > self::TOLERANCIA) {
                $faltante[$linea['articulo_id']] = ($faltante[$linea['articulo_id']] ?? 0.0) + $falta;
            }
        }

        return array_map(
            fn (int $articuloId, float $cantidad): array => ['articulo_id' => $articuloId, 'cantidad' => $cantidad],
            array_keys($faltante),
            array_values($faltante),
        );
    }

    /**
     * Un repuesto pedido en texto pasa al catálogo: se crea su artículo (sin
     * stock, hasta que se compre) y la línea queda ligada a él, para poder
     * pedir su compra y entregarlo con vale.
     */
    public function pasarAlCatalogo(RepuestoOrden $repuesto): Articulo
    {
        $articulo = Articulo::create([
            'codigo' => Articulo::siguienteCodigo(),
            'nombre' => mb_strtoupper(trim($repuesto->descripcion)),
            'unidad' => UnidadMedida::tryFrom((string) $repuesto->unidad) ?? UnidadMedida::Unidad,
            'factor_compra' => 1,
            'activo' => true,
        ]);

        $repuesto->update(['articulo_id' => $articulo->id, 'unidad' => $articulo->unidad->value]);

        return $articulo;
    }

    /**
     * A quién se le entrega: el mecánico responsable de la orden.
     */
    public function responsable(OrdenTrabajo $orden): ?User
    {
        return $orden->mecanicos->first(fn (User $mecanico): bool => (bool) $mecanico->pivot->responsable)
            ?? $orden->mecanicos->first();
    }
}
