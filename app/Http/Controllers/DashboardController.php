<?php

namespace App\Http\Controllers;

use App\Enums\EstadoDocumento;
use App\Enums\EstadoVehiculo;
use App\Enums\TipoVehiculo;
use App\Models\Conductor;
use App\Models\ConductorDocumento;
use App\Models\Vehiculo;
use App\Models\VehiculoDocumento;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Filas máximas del panel de vencimientos. Lo que no entra se encuentra por
     * los semáforos de los listados; el panel solo señala lo más urgente.
     */
    private const MAXIMO_VENCIMIENTOS = 15;

    /**
     * Horizontes que ofrece el filtro del panel de vencimientos, en días.
     */
    private const HORIZONTES = [15, 30];

    public function index(Request $request): Response
    {
        // Cualquier valor fuera del filtro cae al plazo estándar del semáforo.
        $dias = $request->integer('dias');

        if (! in_array($dias, self::HORIZONTES, true)) {
            $dias = VehiculoDocumento::DIAS_AVISO_VENCIMIENTO;
        }

        return Inertia::render('dashboard', [
            'resumen' => [
                'tractos' => Vehiculo::where('tipo', TipoVehiculo::Tracto)->count(),
                'carretas' => Vehiculo::where('tipo', TipoVehiculo::Carreta)->count(),
                'operativos' => Vehiculo::where('estado', EstadoVehiculo::Activo)->count(),
                'conductores' => Conductor::where('activo', true)->count(),
            ],
            'filtros' => ['dias' => $dias],
            'documentosPorVencer' => $this->documentosPorVencer($dias),
        ]);
    }

    /**
     * Documentos vencidos o por vencer dentro del horizonte elegido —papeles de
     * los fierros y licencias de los conductores— ordenados por urgencia, para
     * renovarlos antes de que la unidad o la persona queden inhabilitadas. Los
     * ya vencidos aparecen siempre, sea cual sea el horizonte.
     *
     * El criterio de «vencido» es el mismo `estado()` del semáforo, de modo que
     * un documento que vence hoy se lea igual en todas las pantallas.
     *
     * @return array<int, array<string, mixed>>
     */
    private function documentosPorVencer(int $dias): array
    {
        $limite = now()->addDays($dias)->toDateString();

        $deVehiculos = VehiculoDocumento::query()
            ->with('vehiculo:id,placa')
            // whereHas descarta los documentos de vehículos dados de baja, que
            // ya no obligan a renovar nada.
            ->whereHas('vehiculo')
            ->whereNotNull('fecha_vencimiento')
            ->where('fecha_vencimiento', '<=', $limite)
            ->orderBy('fecha_vencimiento')
            ->take(self::MAXIMO_VENCIMIENTOS)
            ->get()
            ->map(fn (VehiculoDocumento $documento): array => [
                'clave' => "vehiculo-{$documento->id}",
                'titular' => $documento->vehiculo->placa,
                'vehiculo_id' => $documento->vehiculo_id,
                'conductor_id' => null,
                'tipo_label' => $documento->tipo->label(),
                'fecha_vencimiento' => $documento->fecha_vencimiento?->toDateString(),
                'vencido' => $documento->estado() === EstadoDocumento::Vencido,
            ]);

        $deConductores = ConductorDocumento::query()
            ->with('conductor:id,nombres,apellidos')
            ->whereNotNull('fecha_vencimiento')
            ->where('fecha_vencimiento', '<=', $limite)
            ->orderBy('fecha_vencimiento')
            ->take(self::MAXIMO_VENCIMIENTOS)
            ->get()
            ->map(fn (ConductorDocumento $documento): array => [
                'clave' => "conductor-{$documento->id}",
                'titular' => $documento->conductor->nombre_completo,
                'vehiculo_id' => null,
                'conductor_id' => $documento->conductor_id,
                'tipo_label' => $documento->tipo->label(),
                'fecha_vencimiento' => $documento->fecha_vencimiento?->toDateString(),
                'vencido' => $documento->estado() === EstadoDocumento::Vencido,
            ]);

        return $deVehiculos
            ->concat($deConductores)
            ->sortBy('fecha_vencimiento')
            ->take(self::MAXIMO_VENCIMIENTOS)
            ->values()
            ->all();
    }
}
