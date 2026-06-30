<?php

namespace App\Services\Dashboard;

use App\Enums\EstadoVehiculo;
use App\Models\CargaCombustible;
use App\Models\Conductor;
use App\Models\Mantenimiento;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Vehiculo;
use App\Models\VehiculoDocumento;
use App\Services\Mantenimiento\PlanMantenimientoService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class DashboardService
{
    /** Días de anticipación para alertar un documento próximo a vencer. */
    private const HORIZONTE_DOCUMENTOS = 60;

    public function __construct(private PlanMantenimientoService $plan) {}

    /**
     * Arma el resumen del dashboard, acotado a lo que el usuario puede ver.
     *
     * @return array<string, mixed>
     */
    public function para(User $user): array
    {
        $esGestor = $user->hasAnyRole(['admin', 'visor']);

        /** @var Collection<int, Vehiculo> $vehiculos */
        $vehiculos = Vehiculo::query()
            ->visibleParaUsuario($user)
            ->get();

        $vehiculoIds = $vehiculos->pluck('id');

        return [
            'esGestor' => $esGestor,
            'kpis' => $this->kpis($vehiculos, $vehiculoIds, $esGestor),
            'flotaPorEstado' => $this->flotaPorEstado($vehiculos),
            'alertasDocumentos' => $this->alertasDocumentos($vehiculoIds, $user, $esGestor),
            'alertasMantenimiento' => $this->alertasMantenimiento($vehiculos),
            'combustibleSerie' => $this->combustibleSerie($vehiculoIds),
            'actividad' => $this->actividadReciente($vehiculoIds),
        ];
    }

    /**
     * @param  Collection<int, Vehiculo>  $vehiculos
     * @param  Collection<int, int>  $vehiculoIds
     * @return array<string, int|float>
     */
    private function kpis(Collection $vehiculos, Collection $vehiculoIds, bool $esGestor): array
    {
        $inicioMes = CarbonImmutable::today()->startOfMonth();

        $combustibleMes = (float) CargaCombustible::whereIn('vehiculo_id', $vehiculoIds)
            ->where('fecha_carga', '>=', $inicioMes)
            ->sum('costo_total');

        return [
            'vehiculos_total' => $vehiculos->count(),
            'vehiculos_operativos' => $vehiculos->where('estado', EstadoVehiculo::Activo)->count(),
            'vehiculos_mantenimiento' => $vehiculos->where('estado', EstadoVehiculo::EnMantenimiento)->count(),
            'conductores_activos' => $esGestor ? Conductor::where('activo', true)->count() : 0,
            'sucursales' => $esGestor ? Sucursal::where('activa', true)->count() : 0,
            'combustible_mes' => round($combustibleMes, 2),
            'cargas_pendientes' => $esGestor
                ? CargaCombustible::whereIn('vehiculo_id', $vehiculoIds)->whereNull('procesada_en')->count()
                : 0,
        ];
    }

    /**
     * Conteo de vehículos por estado, para el desglose de la flota.
     *
     * @param  Collection<int, Vehiculo>  $vehiculos
     * @return list<array{clave: string, label: string, cantidad: int}>
     */
    private function flotaPorEstado(Collection $vehiculos): array
    {
        return collect(EstadoVehiculo::cases())
            ->map(fn (EstadoVehiculo $estado): array => [
                'clave' => $estado->value,
                'label' => $estado->label(),
                'cantidad' => $vehiculos->where('estado', $estado)->count(),
            ])
            ->filter(fn (array $e): bool => $e['cantidad'] > 0)
            ->values()
            ->all();
    }

    /**
     * Documentos (SOAT, revisión, seguro…) y licencias por vencer o vencidos.
     *
     * @param  Collection<int, int>  $vehiculoIds
     * @return list<array<string, mixed>>
     */
    private function alertasDocumentos(Collection $vehiculoIds, User $user, bool $esGestor): array
    {
        $hoy = CarbonImmutable::today();
        $limite = $hoy->addDays(self::HORIZONTE_DOCUMENTOS);

        $documentos = VehiculoDocumento::query()
            ->whereIn('vehiculo_id', $vehiculoIds)
            ->whereNotNull('fecha_vencimiento')
            ->where('fecha_vencimiento', '<=', $limite)
            ->with('vehiculo:id,placa')
            ->orderBy('fecha_vencimiento')
            ->get()
            ->map(function (VehiculoDocumento $doc) use ($hoy): array {
                $dias = (int) $hoy->diffInDays($doc->fecha_vencimiento->startOfDay(), false);

                return [
                    'id' => "doc-{$doc->id}",
                    'tipo' => $doc->tipo->label(),
                    'referencia' => $doc->vehiculo?->placa ?? '—',
                    'fecha_vencimiento' => $doc->fecha_vencimiento->format('Y-m-d'),
                    'dias_restantes' => $dias,
                    'estado' => $this->estadoVencimiento($dias),
                ];
            });

        $licencias = Conductor::query()
            ->whereNotNull('licencia_vence')
            ->where('licencia_vence', '<=', $limite)
            ->when(! $esGestor, fn ($q) => $q->where('user_id', $user->id))
            ->get()
            ->map(function (Conductor $conductor) use ($hoy): array {
                $dias = (int) $hoy->diffInDays($conductor->licencia_vence->startOfDay(), false);

                return [
                    'id' => "lic-{$conductor->id}",
                    'tipo' => 'Licencia de conducir',
                    'referencia' => $conductor->nombre_completo,
                    'fecha_vencimiento' => $conductor->licencia_vence->format('Y-m-d'),
                    'dias_restantes' => $dias,
                    'estado' => $this->estadoVencimiento($dias),
                ];
            });

        return $documentos
            ->concat($licencias)
            ->sortBy('dias_restantes')
            ->take(10)
            ->values()
            ->all();
    }

    /**
     * Servicios de mantenimiento vencidos o próximos en toda la flota visible.
     *
     * @param  Collection<int, Vehiculo>  $vehiculos
     * @return list<array<string, mixed>>
     */
    private function alertasMantenimiento(Collection $vehiculos): array
    {
        $alertas = [];

        foreach ($vehiculos as $vehiculo) {
            foreach ($this->plan->proximosVencimientos($vehiculo) as $item) {
                if (! in_array($item['status'], ['vencido', 'proximo'], true)) {
                    continue;
                }

                $alertas[] = [
                    'id' => "mant-{$vehiculo->id}-{$item['plantilla_id']}",
                    'vehiculo_id' => $vehiculo->id,
                    'placa' => $vehiculo->placa,
                    'servicio' => $item['nombre'],
                    'status' => $item['status'],
                    'restante_km' => $item['restante_km'],
                    'restante_dias' => $item['restante_dias'],
                ];
            }
        }

        usort($alertas, function (array $a, array $b): int {
            $orden = ['vencido' => 0, 'proximo' => 1];

            return ($orden[$a['status']] <=> $orden[$b['status']])
                ?: (($a['restante_km'] ?? PHP_INT_MAX) <=> ($b['restante_km'] ?? PHP_INT_MAX));
        });

        return array_slice($alertas, 0, 10);
    }

    /**
     * Gasto de combustible de los últimos seis meses.
     *
     * @param  Collection<int, int>  $vehiculoIds
     * @return list<array{mes: string, total: float}>
     */
    private function combustibleSerie(Collection $vehiculoIds): array
    {
        $meses = collect(range(5, 0))->map(
            fn (int $atras): CarbonImmutable => CarbonImmutable::today()->startOfMonth()->subMonths($atras),
        );

        return $meses->map(function (CarbonImmutable $mes) use ($vehiculoIds): array {
            $total = (float) CargaCombustible::whereIn('vehiculo_id', $vehiculoIds)
                ->whereYear('fecha_carga', $mes->year)
                ->whereMonth('fecha_carga', $mes->month)
                ->sum('costo_total');

            return [
                'mes' => ucfirst($mes->locale('es')->isoFormat('MMM')),
                'total' => round($total, 2),
            ];
        })->all();
    }

    /**
     * Últimos movimientos de la flota (cargas y mantenimientos) en una sola
     * línea de tiempo.
     *
     * @param  Collection<int, int>  $vehiculoIds
     * @return list<array<string, mixed>>
     */
    private function actividadReciente(Collection $vehiculoIds): array
    {
        $cargas = CargaCombustible::whereIn('vehiculo_id', $vehiculoIds)
            ->whereNotNull('procesada_en')
            ->with('vehiculo:id,placa')
            ->latest('fecha_carga')
            ->take(6)
            ->get()
            ->map(fn (CargaCombustible $carga): array => [
                'id' => "carga-{$carga->id}",
                'tipo' => 'combustible',
                'placa' => $carga->vehiculo?->placa ?? '—',
                'fecha' => $carga->fecha_carga->toIso8601String(),
                'detalle' => trim(sprintf(
                    '%s gal%s',
                    rtrim(rtrim((string) $carga->galones, '0'), '.'),
                    $carga->costo_total !== null ? ' · S/ '.number_format((float) $carga->costo_total, 2) : '',
                )),
            ]);

        $mantenimientos = Mantenimiento::whereIn('vehiculo_id', $vehiculoIds)
            ->with('vehiculo:id,placa')
            ->latest('fecha_realizado')
            ->take(6)
            ->get()
            ->map(fn (Mantenimiento $mant): array => [
                'id' => "mant-{$mant->id}",
                'tipo' => 'mantenimiento',
                'placa' => $mant->vehiculo?->placa ?? '—',
                'fecha' => $mant->fecha_realizado->toIso8601String(),
                'detalle' => trim(sprintf(
                    '%s%s',
                    $mant->proveedor ?? 'Mantenimiento',
                    $mant->costo_total !== null ? ' · S/ '.number_format((float) $mant->costo_total, 2) : '',
                )),
            ]);

        return $cargas
            ->concat($mantenimientos)
            ->sortByDesc('fecha')
            ->take(7)
            ->values()
            ->all();
    }

    private function estadoVencimiento(int $dias): string
    {
        return match (true) {
            $dias <= 0 => 'vencido',
            $dias <= 15 => 'critico',
            default => 'por_vencer',
        };
    }
}
