<?php

namespace App\Http\Controllers;

use App\Enums\EstadoVehiculo;
use App\Enums\TipoVehiculo;
use App\Models\Conductor;
use App\Models\Vehiculo;
use App\Models\VehiculoDocumento;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('dashboard', [
            'resumen' => [
                'tractos' => Vehiculo::where('tipo', TipoVehiculo::Tracto)->count(),
                'carretas' => Vehiculo::where('tipo', TipoVehiculo::Carreta)->count(),
                'operativos' => Vehiculo::where('estado', EstadoVehiculo::Activo)->count(),
                'conductores' => Conductor::where('activo', true)->count(),
            ],
            'documentosPorVencer' => $this->documentosPorVencer(),
        ]);
    }

    /**
     * Documentos vencidos o que vencen dentro de los próximos 30 días, para
     * renovarlos antes de que la unidad quede inhabilitada para circular.
     *
     * @return array<int, array<string, mixed>>
     */
    private function documentosPorVencer(): array
    {
        return VehiculoDocumento::query()
            ->with('vehiculo:id,placa,tipo')
            ->whereNotNull('fecha_vencimiento')
            ->where('fecha_vencimiento', '<=', now()->addDays(30))
            ->orderBy('fecha_vencimiento')
            ->take(15)
            ->get()
            ->map(fn (VehiculoDocumento $documento): array => [
                'id' => $documento->id,
                'vehiculo_id' => $documento->vehiculo_id,
                'placa' => $documento->vehiculo?->placa ?? '—',
                'tipo_label' => $documento->tipo->label(),
                'fecha_vencimiento' => $documento->fecha_vencimiento?->toDateString(),
                'vencido' => $documento->fecha_vencimiento?->isPast() ?? false,
            ])
            ->all();
    }
}
