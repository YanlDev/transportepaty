<?php

namespace App\Http\Controllers;

use App\Enums\TipoVehiculo;
use App\Http\Requests\StorePlantillaMantenimientoRequest;
use App\Models\PlantillaMantenimiento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlantillaMantenimientoController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', PlantillaMantenimiento::class);

        $filtros = [
            'buscar' => $request->string('buscar')->trim()->value(),
        ];

        $plantillas = PlantillaMantenimiento::query()
            ->when($filtros['buscar'], function ($query, string $buscar): void {
                $query->where(function ($query) use ($buscar): void {
                    $query->whereLike('nombre', "%{$buscar}%", caseSensitive: false)
                        ->orWhereLike('marca', "%{$buscar}%", caseSensitive: false)
                        ->orWhereLike('modelo', "%{$buscar}%", caseSensitive: false);
                });
            })
            ->orderBy('marca')
            ->orderBy('modelo')
            ->orderBy('orden')
            ->orderBy('nombre')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('mantenedor/plantillas-mantenimiento', [
            'plantillas' => $plantillas,
            'filtros' => $filtros,
            'tiposVehiculo' => collect(TipoVehiculo::cases())->map(fn (TipoVehiculo $t): array => [
                'value' => $t->value,
                'label' => $t->label(),
            ]),
        ]);
    }

    public function store(StorePlantillaMantenimientoRequest $request): RedirectResponse
    {
        $this->authorize('create', PlantillaMantenimiento::class);

        PlantillaMantenimiento::create($request->validated());

        return to_route('mantenedor.plantillas.index')
            ->with('toast', ['type' => 'success', 'message' => 'Plantilla creada correctamente.']);
    }

    public function update(StorePlantillaMantenimientoRequest $request, PlantillaMantenimiento $plantilla): RedirectResponse
    {
        $this->authorize('update', $plantilla);

        $plantilla->update($request->validated());

        return to_route('mantenedor.plantillas.index')
            ->with('toast', ['type' => 'success', 'message' => 'Plantilla actualizada correctamente.']);
    }

    public function destroy(PlantillaMantenimiento $plantilla): RedirectResponse
    {
        $this->authorize('delete', $plantilla);

        $plantilla->delete();

        return to_route('mantenedor.plantillas.index')
            ->with('toast', ['type' => 'success', 'message' => 'Plantilla eliminada correctamente.']);
    }
}
