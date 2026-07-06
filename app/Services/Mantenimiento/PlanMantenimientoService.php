<?php

namespace App\Services\Mantenimiento;

use App\Models\CargaCombustible;
use App\Models\Mantenimiento;
use App\Models\MantenimientoItem;
use App\Models\PlantillaMantenimiento;
use App\Models\Vehiculo;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class PlanMantenimientoService
{
    public const MARGEN_KM_PROXIMO = 500;

    public const MARGEN_KM_ATENCION = 1000;

    public const MARGEN_DIAS_PROXIMO = 15;

    public const MARGEN_DIAS_ATENCION = 30;

    /**
     * Odómetro vigente del vehículo, considerando GPS o fuentes alternativas.
     */
    public function odometroVigente(Vehiculo $vehiculo): int
    {
        if ($vehiculo->tieneGps()) {
            return $vehiculo->kilometraje;
        }

        return max(
            $vehiculo->kilometraje,
            CargaCombustible::where('vehiculo_id', $vehiculo->id)
                ->whereNotNull('odometro')
                ->max('odometro') ?? 0,
            Mantenimiento::where('vehiculo_id', $vehiculo->id)
                ->max('odometro') ?? 0,
        );
    }

    /**
     * Plantillas aplicables a un vehículo siguiendo la cascada:
     * 1. Específica por marca + modelo (LIKE)
     * 2. Por tipo_vehiculo
     * 3. Genérica (todo null)
     *
     * @return Collection<int, PlantillaMantenimiento>
     */
    public function plantillasParaVehiculo(Vehiculo $vehiculo): Collection
    {
        $especificas = PlantillaMantenimiento::where('activo', true)
            ->where('marca', $vehiculo->marca)
            ->where(function ($q) use ($vehiculo): void {
                $q->where('modelo', $vehiculo->modelo)
                    ->orWhereRaw('? LIKE CONCAT(modelo, \'%\')', [$vehiculo->modelo]);
            })
            ->orderBy('orden')
            ->get();

        if ($especificas->isNotEmpty()) {
            return $especificas;
        }

        $porTipo = PlantillaMantenimiento::where('activo', true)
            ->whereNull('marca')
            ->whereNull('modelo')
            ->where('tipo_vehiculo', $vehiculo->tipo->value)
            ->orderBy('orden')
            ->get();

        if ($porTipo->isNotEmpty()) {
            return $porTipo;
        }

        return PlantillaMantenimiento::where('activo', true)
            ->whereNull('marca')
            ->whereNull('modelo')
            ->whereNull('tipo_vehiculo')
            ->orderBy('orden')
            ->get();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function proximosVencimientos(Vehiculo $vehiculo): array
    {
        $odo = $this->odometroVigente($vehiculo);
        $hoy = CarbonImmutable::today();
        $plantillas = $this->plantillasParaVehiculo($vehiculo);

        return $plantillas->map(function (PlantillaMantenimiento $plantilla) use ($vehiculo, $odo, $hoy): ?array {
            $ultimo = Mantenimiento::where('vehiculo_id', $vehiculo->id)
                ->whereHas('items', fn ($q) => $q->where('plantilla_id', $plantilla->id))
                ->orderByDesc('odometro')
                ->first();

            // Servicio único (primer mantenimiento): mientras no se haya hecho
            // se muestra a su km objetivo; al realizarse desaparece del plan.
            // Sólo aplica a unidades que ingresaron nuevas a la flota.
            if ($plantilla->una_vez) {
                if (! $this->servicioUnicoAplica($plantilla, $vehiculo)) {
                    return null;
                }

                return $ultimo === null
                    ? $this->vencimientoServicioUnico($plantilla, $odo)
                    : null;
            }

            if ($ultimo === null) {
                return $this->vencimientoVacio($plantilla);
            }

            $ultimoOdometro = $ultimo->odometro;
            $ultimaFecha = $ultimo->fecha_realizado;

            $statusKm = null;
            $restanteKm = null;
            $proximoKm = null;

            if ($plantilla->intervalo_km !== null) {
                $proximoKm = $ultimoOdometro + $plantilla->intervalo_km;
                $restanteKm = $proximoKm - $odo;
                $statusKm = $this->statusPorKm($restanteKm);
            }

            $statusTiempo = null;
            $restanteDias = null;
            $proximaFecha = null;

            if ($plantilla->intervalo_meses !== null) {
                $proximaFecha = $ultimaFecha->copy()->addMonths($plantilla->intervalo_meses);
                // Días restantes con signo: positivo = futuro, <=0 = vencido.
                $restanteDias = (int) $hoy->diffInDays($proximaFecha->copy()->startOfDay(), false);
                $statusTiempo = $this->statusPorDias($restanteDias);
            }

            $status = $this->peorStatus($statusKm, $statusTiempo);

            $intervaloKm = $plantilla->intervalo_km;
            $progreso = $intervaloKm !== null && $intervaloKm > 0
                ? max(0, min(1, ($intervaloKm - max($restanteKm ?? 0, 0)) / $intervaloKm))
                : 0;

            return [
                'plantilla_id' => $plantilla->id,
                'nombre' => $plantilla->nombre,
                'tipo_mantenimiento' => $plantilla->tipo_mantenimiento,
                'intervalo_km' => $plantilla->intervalo_km,
                'intervalo_meses' => $plantilla->intervalo_meses,
                'ultimo_odometro' => $ultimoOdometro,
                'ultima_fecha' => $ultimaFecha->format('Y-m-d'),
                'proximo_odometro' => $proximoKm,
                'proximo_fecha' => $proximaFecha?->format('Y-m-d'),
                'restante_km' => $restanteKm,
                'restante_dias' => $restanteDias,
                'progreso' => round($progreso, 2),
                'status' => $status,
                'es_unico' => false,
            ];
        })->filter()->values()->all();
    }

    /**
     * Conteo de servicios por estado, para el resumen del plan.
     *
     * @param  array<int, array<string, mixed>>  $proximos
     * @return array{al_dia: int, proximo: int, vencido: int, critico: int}
     */
    public function conteoEstados(array $proximos): array
    {
        $conteo = ['al_dia' => 0, 'proximo' => 0, 'vencido' => 0, 'critico' => 0];

        foreach ($proximos as $p) {
            match ($p['status']) {
                'vencido' => $conteo['vencido']++,
                'proximo', 'atencion' => $conteo['proximo']++,
                default => $conteo['al_dia']++,
            };
        }

        return $conteo;
    }

    /**
     * @return array{
     *     total_mantenimientos: int,
     *     total_gastado: float,
     *     ultimo_costo: float|null,
     *     costo_promedio: float|null,
     *     mas_comun: string|null
     * }
     */
    public function estadisticas(Vehiculo $vehiculo): array
    {
        $visitas = Mantenimiento::where('vehiculo_id', $vehiculo->id);

        $total = (clone $visitas)->count();
        $totalGastado = (float) ((clone $visitas)->sum('costo_total') ?? 0);
        $ultimo = (clone $visitas)->latest('fecha_realizado')->first();

        $masComun = MantenimientoItem::whereIn(
            'mantenimiento_id',
            Mantenimiento::where('vehiculo_id', $vehiculo->id)->select('id'),
        )
            ->select('nombre')
            ->groupBy('nombre')
            ->orderByRaw('COUNT(*) DESC')
            ->value('nombre');

        return [
            'total_mantenimientos' => $total,
            'total_gastado' => $totalGastado,
            'ultimo_costo' => $ultimo?->costo_total !== null ? (float) $ultimo->costo_total : null,
            'costo_promedio' => $total > 0 ? round($totalGastado / $total, 2) : null,
            'mas_comun' => $masComun,
        ];
    }

    /**
     * Costo de propiedad del vehículo en un año: combustible + mantenimiento.
     *
     * @return array{
     *     anio: int,
     *     total: float,
     *     categorias: list<array{clave: string, label: string, monto: float}>
     * }
     */
    public function costosAnio(Vehiculo $vehiculo, int $anio): array
    {
        $combustible = (float) (CargaCombustible::where('vehiculo_id', $vehiculo->id)
            ->whereYear('fecha_carga', $anio)
            ->sum('costo_total') ?? 0);

        $mantenimiento = (float) (Mantenimiento::where('vehiculo_id', $vehiculo->id)
            ->whereYear('fecha_realizado', $anio)
            ->sum('costo_total') ?? 0);

        $categorias = collect([
            ['clave' => 'combustible', 'label' => 'Combustible', 'monto' => round($combustible, 2)],
            ['clave' => 'mantenimiento', 'label' => 'Mantenimiento', 'monto' => round($mantenimiento, 2)],
        ])
            ->filter(fn (array $c): bool => $c['monto'] > 0)
            ->values()
            ->all();

        return [
            'anio' => $anio,
            'total' => round($combustible + $mantenimiento, 2),
            'categorias' => $categorias,
        ];
    }

    public function odometroMinimo(Vehiculo $vehiculo): int
    {
        return max(
            $vehiculo->kilometraje,
            CargaCombustible::where('vehiculo_id', $vehiculo->id)
                ->whereNotNull('odometro')
                ->max('odometro') ?? 0,
            Mantenimiento::where('vehiculo_id', $vehiculo->id)
                ->max('odometro') ?? 0,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function vencimientoVacio(PlantillaMantenimiento $plantilla): array
    {
        return [
            'plantilla_id' => $plantilla->id,
            'nombre' => $plantilla->nombre,
            'tipo_mantenimiento' => $plantilla->tipo_mantenimiento,
            'intervalo_km' => $plantilla->intervalo_km,
            'intervalo_meses' => $plantilla->intervalo_meses,
            'ultimo_odometro' => null,
            'ultima_fecha' => null,
            'proximo_odometro' => $plantilla->intervalo_km,
            'proximo_fecha' => null,
            'restante_km' => $plantilla->intervalo_km,
            'restante_dias' => null,
            'progreso' => 0,
            'status' => 'sin_historial',
            'es_unico' => false,
        ];
    }

    /**
     * Un servicio único por km (p. ej. "inspección inicial 1000 km") sólo aplica
     * a unidades que ingresaron nuevas a la flota: su odómetro de alta debe
     * estar por debajo del km objetivo. Un vehículo registrado con 40.000 km no
     * es candidato. Los servicios únicos sólo por tiempo (sin km) aplican siempre.
     */
    private function servicioUnicoAplica(PlantillaMantenimiento $plantilla, Vehiculo $vehiculo): bool
    {
        if ($plantilla->intervalo_km === null) {
            return true;
        }

        $inicial = $vehiculo->kilometraje_inicial ?? $vehiculo->kilometraje;

        return $inicial <= $plantilla->intervalo_km;
    }

    /**
     * Vencimiento de un servicio único (primer mantenimiento): vence al llegar
     * al km objetivo (`intervalo_km`), restando el odómetro actual.
     *
     * @return array<string, mixed>
     */
    private function vencimientoServicioUnico(PlantillaMantenimiento $plantilla, int $odo): array
    {
        $objetivoKm = $plantilla->intervalo_km;

        $restanteKm = null;
        $statusKm = null;
        $progreso = 0.0;

        if ($objetivoKm !== null) {
            $restanteKm = $objetivoKm - $odo;
            $statusKm = $this->statusPorKm($restanteKm);
            $progreso = $objetivoKm > 0
                ? max(0.0, min(1.0, ($objetivoKm - max($restanteKm, 0)) / $objetivoKm))
                : 0.0;
        }

        return [
            'plantilla_id' => $plantilla->id,
            'nombre' => $plantilla->nombre,
            'tipo_mantenimiento' => $plantilla->tipo_mantenimiento,
            'intervalo_km' => $plantilla->intervalo_km,
            'intervalo_meses' => $plantilla->intervalo_meses,
            'ultimo_odometro' => null,
            'ultima_fecha' => null,
            'proximo_odometro' => $objetivoKm,
            'proximo_fecha' => null,
            'restante_km' => $restanteKm,
            'restante_dias' => null,
            'progreso' => round($progreso, 2),
            'status' => $statusKm ?? 'sin_historial',
            'es_unico' => true,
        ];
    }

    private function statusPorKm(int $restante): string
    {
        if ($restante <= 0) {
            return 'vencido';
        }

        if ($restante <= self::MARGEN_KM_PROXIMO) {
            return 'proximo';
        }

        if ($restante <= self::MARGEN_KM_ATENCION) {
            return 'atencion';
        }

        return 'ok';
    }

    private function statusPorDias(int $restante): string
    {
        if ($restante <= 0) {
            return 'vencido';
        }

        if ($restante <= self::MARGEN_DIAS_PROXIMO) {
            return 'proximo';
        }

        if ($restante <= self::MARGEN_DIAS_ATENCION) {
            return 'atencion';
        }

        return 'ok';
    }

    private function peorStatus(?string $a, ?string $b): string
    {
        $prioridad = ['vencido' => 0, 'proximo' => 1, 'atencion' => 2, 'ok' => 3];

        $scoreA = $a !== null ? ($prioridad[$a] ?? 3) : 3;
        $scoreB = $b !== null ? ($prioridad[$b] ?? 3) : 3;

        if ($scoreA <= $scoreB) {
            return $a ?? $b ?? 'ok';
        }

        return $b ?? $a ?? 'ok';
    }
}
