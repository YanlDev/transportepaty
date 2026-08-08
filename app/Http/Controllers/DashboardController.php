<?php

namespace App\Http\Controllers;

use App\Enums\EstadoDocumento;
use App\Enums\EstadoVehiculo;
use App\Enums\TipoNovedad;
use App\Enums\TipoVehiculo;
use App\Models\Conductor;
use App\Models\ConductorDocumento;
use App\Models\Novedad;
use App\Models\Vehiculo;
use App\Models\VehiculoDocumento;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
                'novedadesActivas' => Novedad::vigentes()->count(),
                'documentosVencidos' => $this->documentosVencidos(),
            ],
            'filtros' => ['dias' => $dias],
            'documentosPorVencer' => $this->documentosPorVencer($dias),
            'estadoFlota' => $this->estadoFlota(),
            'novedadesPorTipo' => $this->novedadesPorTipo(),
            'saludDocumental' => $this->saludDocumental(),
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

    /**
     * Total de papeles ya vencidos, de fierros y de conductores, sin el tope de
     * filas que aplica al panel: es el número que va en la tarjeta de alerta.
     */
    private function documentosVencidos(): int
    {
        $hoy = now()->toDateString();

        $deVehiculos = VehiculoDocumento::query()
            ->whereHas('vehiculo')
            ->whereNotNull('fecha_vencimiento')
            ->where('fecha_vencimiento', '<', $hoy)
            ->count();

        $deConductores = ConductorDocumento::query()
            ->whereNotNull('fecha_vencimiento')
            ->where('fecha_vencimiento', '<', $hoy)
            ->count();

        return $deVehiculos + $deConductores;
    }

    /**
     * Cuántas unidades hay en cada estado operativo, en el orden fijo del enum.
     * Incluye los estados en cero para que la composición de la flota se lea
     * completa de un vistazo, no solo lo que tiene unidades.
     *
     * @return list<array{estado: string, label: string, valor: int}>
     */
    private function estadoFlota(): array
    {
        $conteos = Vehiculo::query()
            ->selectRaw('estado, count(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        return array_map(
            fn (EstadoVehiculo $estado): array => [
                'estado' => $estado->value,
                'label' => $estado->label(),
                'valor' => (int) ($conteos[$estado->value] ?? 0),
            ],
            EstadoVehiculo::cases(),
        );
    }

    /**
     * Novedades vigentes agrupadas por tipo: lo que hoy está sacando unidades de
     * la programación, y por qué motivo. En el orden fijo del enum para que el
     * mismo tipo caiga siempre en la misma posición entre una visita y la
     * siguiente.
     *
     * @return list<array{tipo: string, label: string, valor: int}>
     */
    private function novedadesPorTipo(): array
    {
        $conteos = Novedad::query()
            ->vigentes()
            ->selectRaw('tipo, count(*) as total')
            ->groupBy('tipo')
            ->pluck('total', 'tipo');

        return array_map(
            fn (TipoNovedad $tipo): array => [
                'tipo' => $tipo->value,
                'label' => $tipo->label(),
                'valor' => (int) ($conteos[$tipo->value] ?? 0),
            ],
            TipoNovedad::cases(),
        );
    }

    /**
     * Semáforo documental de la flota y de los conductores, contado por color.
     * Se apoya en `estadoDocumental()` de cada modelo —la misma regla que pinta
     * los listados— así que el resumen nunca se puede desalinear del semáforo
     * que ve cada unidad o conductor por separado.
     *
     * @return list<array{entidad: string, label: string, verde: int, ambar: int, rojo: int}>
     */
    private function saludDocumental(): array
    {
        $semaforosVehiculos = Vehiculo::query()
            ->with('documentos:id,vehiculo_id,tipo,fecha_vencimiento')
            ->get()
            ->map(fn (Vehiculo $vehiculo): string => $vehiculo->estadoDocumental()['semaforo']);

        $semaforosConductores = Conductor::query()
            ->with('documentos:id,conductor_id,tipo,fecha_vencimiento')
            ->get()
            ->map(fn (Conductor $conductor): string => $conductor->estadoDocumental()['semaforo']);

        return [
            $this->contarSemaforos('vehiculos', 'Vehículos', $semaforosVehiculos),
            $this->contarSemaforos('conductores', 'Conductores', $semaforosConductores),
        ];
    }

    /**
     * @param  Collection<int, string>  $semaforos
     * @return array{entidad: string, label: string, verde: int, ambar: int, rojo: int}
     */
    private function contarSemaforos(string $entidad, string $label, Collection $semaforos): array
    {
        $conteos = $semaforos->countBy();

        return [
            'entidad' => $entidad,
            'label' => $label,
            'verde' => (int) ($conteos['verde'] ?? 0),
            'ambar' => (int) ($conteos['ambar'] ?? 0),
            'rojo' => (int) ($conteos['rojo'] ?? 0),
        ];
    }
}
