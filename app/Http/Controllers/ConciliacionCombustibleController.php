<?php

namespace App\Http\Controllers;

use App\Models\Abastecimiento;
use App\Models\Vuelta;
use App\Services\RelojOperativo;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La conciliación del combustible de un mes: por grifo, lo autorizado
 * contra lo despachado (los vouchers) y lo que costó; las autorizaciones que
 * siguen sin voucher; y el rendimiento de cada unidad en los tickets que
 * llegaron a la base. Es lo que se cruza con el estado de cuenta de Repsol.
 */
class ConciliacionCombustibleController extends Controller
{
    /** Desde cuántos días sin voucher una autorización se marca como atrasada. */
    private const DIAS_SIN_VOUCHER = 3;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Abastecimiento::class);

        $mes = $this->mes($request);
        $desde = $mes->startOfMonth();
        $hasta = $mes->endOfMonth();

        $registros = Abastecimiento::query()
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->with(['vehiculo:id,placa', 'punto:id,nombre', 'vuelta:id,codigo'])
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();

        $vigentes = $registros->reject(fn (Abastecimiento $abastecimiento): bool => $abastecimiento->estaAnulado());
        $hoy = RelojOperativo::fechaDeHoy();

        return Inertia::render('abastecimiento/conciliacion', [
            'mes' => $mes->format('Y-m'),
            'totales' => [
                'autorizado' => round((float) $vigentes->sum('galones'), 2),
                'despachado' => round((float) $vigentes->sum('galones_despachados'), 2),
                'importe' => round((float) $vigentes->sum('importe'), 2),
                'sin_voucher' => $vigentes->filter(fn (Abastecimiento $abastecimiento): bool => ! $abastecimiento->tieneDespacho())->count(),
                'anulados' => $registros->count() - $vigentes->count(),
            ],
            'porGrifo' => $this->porGrifo($vigentes),
            'sinVoucher' => array_values($vigentes
                ->filter(fn (Abastecimiento $abastecimiento): bool => ! $abastecimiento->tieneDespacho())
                ->map(fn (Abastecimiento $abastecimiento): array => [
                    'id' => $abastecimiento->id,
                    'fecha' => $abastecimiento->fecha->toDateString(),
                    'placa' => $abastecimiento->vehiculo->placa,
                    'ticket' => $abastecimiento->vuelta?->codigo,
                    'vuelta_id' => $abastecimiento->vuelta_id,
                    'punto' => $abastecimiento->nombrePunto(),
                    'galones' => (float) $abastecimiento->galones,
                    'atrasado' => $abastecimiento->fecha->lt($hoy->subDays(self::DIAS_SIN_VOUCHER)),
                ])
                ->all()),
            'rendimiento' => $this->rendimiento($desde, $hasta),
        ]);
    }

    /**
     * @param  Collection<int, Abastecimiento>  $vigentes
     * @return list<array{punto: string, autorizaciones: int, autorizado: float, despachado: float, diferencia: float, importe: float, sin_voucher: int, precio_promedio: float|null}>
     */
    private function porGrifo(Collection $vigentes): array
    {
        return array_values($vigentes
            ->groupBy(fn (Abastecimiento $abastecimiento): string => $abastecimiento->nombrePunto() ?? 'Sin punto')
            ->map(function (Collection $delGrifo, string $punto): array {
                $despachado = (float) $delGrifo->sum('galones_despachados');
                $importe = (float) $delGrifo->sum('importe');

                return [
                    'punto' => $punto,
                    'autorizaciones' => $delGrifo->count(),
                    'autorizado' => round((float) $delGrifo->sum('galones'), 2),
                    'despachado' => round($despachado, 2),
                    'diferencia' => round($despachado - (float) $delGrifo->filter(fn (Abastecimiento $abastecimiento): bool => $abastecimiento->tieneDespacho())->sum('galones'), 2),
                    'importe' => round($importe, 2),
                    'sin_voucher' => $delGrifo->filter(fn (Abastecimiento $abastecimiento): bool => ! $abastecimiento->tieneDespacho())->count(),
                    'precio_promedio' => $despachado > 0 ? round($importe / $despachado, 3) : null,
                ];
            })
            ->sortByDesc('autorizado')
            ->all());
    }

    /**
     * Km por galón de cada unidad, en los tickets que llegaron a la base en
     * el mes y tienen el km de salida (OK de taller) y el de llegada.
     *
     * @return list<array{placa: string, tickets: int, km: int, galones: float, rendimiento: float|null}>
     */
    private function rendimiento(CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $tickets = Vuelta::query()
            ->whereBetween('llegada_el', [$desde->toDateString(), $hasta->toDateString()])
            ->whereNotNull('km_llegada')
            ->with(['tracto:id,placa', 'revisiones', 'abastecimientos' => fn ($query) => $query->vigentes()])
            ->get();

        return array_values($tickets
            ->map(function (Vuelta $vuelta): ?array {
                $salida = $vuelta->revisiones->first(fn ($revision): bool => $revision->esApta())?->kilometraje;

                if ($salida === null || $vuelta->km_llegada < $salida) {
                    return null;
                }

                return [
                    'placa' => $vuelta->tracto->placa,
                    'km' => $vuelta->km_llegada - $salida,
                    'galones' => (float) $vuelta->abastecimientos->sum('galones_despachados'),
                ];
            })
            ->filter()
            ->groupBy('placa')
            ->map(function (Collection $deLaUnidad, string $placa): array {
                $km = (int) $deLaUnidad->sum('km');
                $galones = (float) $deLaUnidad->sum('galones');

                return [
                    'placa' => $placa,
                    'tickets' => $deLaUnidad->count(),
                    'km' => $km,
                    'galones' => round($galones, 2),
                    'rendimiento' => $galones > 0 ? round($km / $galones, 2) : null,
                ];
            })
            ->sortBy('rendimiento')
            ->all());
    }

    private function mes(Request $request): CarbonImmutable
    {
        $pedido = $request->string('mes')->toString();

        return preg_match('/^\d{4}-\d{2}$/', $pedido) === 1
            ? CarbonImmutable::createFromFormat('Y-m-d', $pedido.'-01')->startOfDay()
            : RelojOperativo::fechaDeHoy()->startOfMonth();
    }
}
