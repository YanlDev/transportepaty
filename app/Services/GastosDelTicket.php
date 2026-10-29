<?php

namespace App\Services;

use App\Models\Abastecimiento;
use App\Models\HistorialCambio;
use App\Models\Viatico;
use App\Models\Vuelta;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Lo que se gastó en un ticket contra lo que dice la pizarra: el combustible
 * por tramo y por punto, los viáticos depositados (y los anulados, con sus
 * movimientos) y lo que suma en soles. Lo leen la bandeja de abastecimiento
 * y el expediente del ticket: los dos ven exactamente lo mismo.
 */
class GastosDelTicket
{
    public function __construct(private readonly DotacionVuelta $dotacion) {}

    /**
     * Los gastos de varios tickets, por id. Las consultas van juntas: no una
     * por ticket. Los tickets tienen que venir con `paradas.viajes`.
     *
     * @param  Collection<int, Vuelta>  $vueltas
     * @return array<int, array{
     *     tarjetas: list<array{id: int, nombre: string, color: string|null, parada_id: int}>,
     *     combustible: list<array{punto_id: int|null, punto: string, previsto: float|null, lleno: bool, registrado: float, falta: float|null}>,
     *     total_previsto: float|null,
     *     total_registrado: float,
     *     tramos: list<array{sentido: string, previsto: float|null, registrado: float, falta: float|null, puntos: list<array{punto: string, previsto: float|null, lleno: bool}>}>,
     *     viaticos: list<array<string, mixed>>,
     *     viatico_pendiente: float,
     *     viatico_depositado: float,
     *     viaticos_anulados: list<array<string, mixed>>,
     *     movimientos_viaticos: list<array<string, mixed>>,
     *     importe_registrado: float,
     *     abastecimientos: list<array<string, mixed>>
     * }>
     */
    public function de(Collection $vueltas): array
    {
        $ids = $vueltas->pluck('id');

        $registrados = Abastecimiento::query()
            ->whereIn('vuelta_id', $ids)
            ->with(['punto:id,nombre', 'registradoPor:id,name'])
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();

        $viaticos = Viatico::query()
            ->whereIn('vuelta_id', $ids)
            ->with(['registradoPor:id,name', 'anuladoPor:id,name'])
            ->orderBy('id')
            ->get();

        $movimientos = HistorialCambio::query()
            ->where('modelo', class_basename(Viatico::class))
            ->whereIn('modelo_id', $viaticos->pluck('id'))
            ->with('usuario:id,name')
            ->latest('id')
            ->get()
            ->groupBy(fn (HistorialCambio $movimiento): int => (int) $viaticos->firstWhere('id', $movimiento->modelo_id)?->vuelta_id);

        $gastos = [];

        foreach ($vueltas as $vuelta) {
            $delTicket = $registrados->where('vuelta_id', $vuelta->id)->values();
            $dotacion = $this->dotacion->de($vuelta, $delTicket);

            $gastos[$vuelta->id] = [
                ...$dotacion,
                ...$this->viaticos(
                    $dotacion['viaticos'],
                    $viaticos->where('vuelta_id', $vuelta->id)->values(),
                    $movimientos->get($vuelta->id, collect()),
                ),
                'importe_registrado' => round((float) $delTicket->sum('importe'), 2),
                'abastecimientos' => array_values($delTicket
                    ->map(fn (Abastecimiento $abastecimiento): array => [
                        'id' => $abastecimiento->id,
                        'fecha' => $abastecimiento->fecha->toDateString(),
                        'punto' => $abastecimiento->punto?->nombre,
                        'galones' => (float) $abastecimiento->galones,
                        'tanque_lleno' => $abastecimiento->tanque_lleno,
                        'precio_galon' => $abastecimiento->precio_galon === null ? null : (float) $abastecimiento->precio_galon,
                        'importe' => $abastecimiento->importe === null ? null : (float) $abastecimiento->importe,
                        'registrado_por' => $abastecimiento->registradoPor?->name,
                    ])
                    ->all()),
            ];
        }

        return $gastos;
    }

    /**
     * Los viáticos del ticket: cada uno de la pizarra con su depósito o
     * pendiente, los imprevistos, los anulados y los movimientos de todos.
     *
     * @param  list<array{linea_id: int, concepto: string, tipo: string, monto: float}>  $dePizarra
     * @param  EloquentCollection<int, Viatico>  $delTicket
     * @param  Collection<int, HistorialCambio>  $movimientos
     * @return array{viaticos: list<array<string, mixed>>, viatico_pendiente: float, viatico_depositado: float, viaticos_anulados: list<array<string, mixed>>, movimientos_viaticos: list<array<string, mixed>>}
     */
    private function viaticos(array $dePizarra, EloquentCollection $delTicket, Collection $movimientos): array
    {
        $filas = $this->dotacion->viaticosContraDepositos(
            $dePizarra,
            $delTicket->reject(fn (Viatico $viatico): bool => $viatico->estaAnulado())->values(),
        );

        return [
            'viaticos' => array_map(fn (array $fila): array => [
                'linea_id' => $fila['linea_id'],
                'concepto' => $fila['concepto'],
                'previsto' => $fila['previsto'],
                'deposito' => $fila['deposito'] === null ? null : [
                    'id' => $fila['deposito']->id,
                    'monto' => (float) $fila['deposito']->monto,
                    'fecha' => $fila['deposito']->fecha->toDateString(),
                    'numero_operacion' => $fila['deposito']->numero_operacion,
                    'observacion' => $fila['deposito']->observacion,
                    'imprevisto' => $fila['deposito']->linea_regla_abastecimiento_id === null,
                    'registrado_por' => $fila['deposito']->registradoPor?->name,
                ],
            ], $filas),
            'viatico_pendiente' => round((float) collect($filas)->whereNull('deposito')->sum('previsto'), 2),
            'viatico_depositado' => round((float) $delTicket->reject(fn (Viatico $viatico): bool => $viatico->estaAnulado())->sum('monto'), 2),
            'viaticos_anulados' => array_values($delTicket
                ->filter(fn (Viatico $viatico): bool => $viatico->estaAnulado())
                ->map(fn (Viatico $viatico): array => [
                    'id' => $viatico->id,
                    'concepto' => $viatico->concepto,
                    'monto' => (float) $viatico->monto,
                    'fecha' => $viatico->fecha->toDateString(),
                    'motivo' => $viatico->motivo_anulacion,
                    'anulado_por' => $viatico->anuladoPor?->name,
                    'anulado_at' => $viatico->anulado_at?->toIso8601String(),
                ])
                ->all()),
            'movimientos_viaticos' => array_values($movimientos
                ->map(fn (HistorialCambio $movimiento): array => [
                    'id' => $movimiento->id,
                    'fecha' => $movimiento->created_at->toIso8601String(),
                    'usuario' => $movimiento->usuario?->name,
                    'accion' => $movimiento->accion,
                    'etiqueta' => $movimiento->etiqueta,
                    'cambios' => $movimiento->cambios ?? [],
                ])
                ->all()),
        ];
    }
}
