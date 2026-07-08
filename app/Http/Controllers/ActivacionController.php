<?php

namespace App\Http\Controllers;

use App\Enums\ResultadoActivacion;
use App\Http\Requests\StoreActivacionRequest;
use App\Models\Activacion;
use App\Models\Vehiculo;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ActivacionController extends Controller
{
    /**
     * Historial de activaciones periódicas de la unidad.
     */
    public function index(Vehiculo $vehiculo): Response
    {
        $this->authorize('verHistorial', [Activacion::class, $vehiculo]);

        $activaciones = $vehiculo->activaciones()
            ->with(['registradaPor:id,name', 'conductor:id,nombres,apellidos'])
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('vehiculos/activaciones', [
            'vehiculo' => [
                'id' => $vehiculo->id,
                'placa' => $vehiculo->placa,
                'marca' => $vehiculo->marca,
                'modelo' => $vehiculo->modelo,
                'kilometraje' => $vehiculo->kilometraje,
            ],
            'activaciones' => $activaciones->map(fn (Activacion $activacion): array => $this->aDto($activacion))->values(),
            'resultados' => ResultadoActivacion::options(),
            'reposoDias' => config('flota.reposo_dias'),
            'puede' => [
                'registrar' => $this->can('registrar', [Activacion::class, $vehiculo]),
                'gestionar' => request()->user()?->hasRole('admin') ?? false,
            ],
        ]);
    }

    public function store(StoreActivacionRequest $request, Vehiculo $vehiculo): RedirectResponse
    {
        $this->authorize('registrar', [Activacion::class, $vehiculo]);

        $kilometraje = $request->validated('kilometraje');

        $vehiculo->activaciones()->create([
            'conductor_id' => $vehiculo->conductor_id,
            'registrada_por' => $request->user()->id,
            'fecha' => $request->date('fecha') ?? now(),
            'kilometraje' => $kilometraje,
            'resultado' => $request->validated('resultado'),
            'observaciones' => $request->validated('observaciones'),
        ]);

        // Un recorrido de activación puede avanzar el odómetro en unidades
        // sin GPS; en las que tienen GPS lo lleva la sincronización.
        $vehiculo->actualizarKilometrajePorCarga($kilometraje);

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Activación registrada correctamente.',
        ]);
    }

    public function destroy(Vehiculo $vehiculo, Activacion $activacion): RedirectResponse
    {
        abort_unless($activacion->vehiculo_id === $vehiculo->id, 404);

        $this->authorize('delete', $activacion);

        $activacion->delete();

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Activación eliminada correctamente.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function aDto(Activacion $activacion): array
    {
        return [
            'id' => $activacion->id,
            'fecha' => $activacion->fecha->toIso8601String(),
            'kilometraje' => $activacion->kilometraje,
            'resultado' => $activacion->resultado->value,
            'resultado_label' => $activacion->resultado->label(),
            'observaciones' => $activacion->observaciones,
            'conductor' => $activacion->conductor?->nombre_completo,
            'registrada_por' => $activacion->registradaPor?->name,
        ];
    }

    private function can(string $ability, mixed $arguments): bool
    {
        return request()->user()?->can($ability, $arguments) ?? false;
    }
}
