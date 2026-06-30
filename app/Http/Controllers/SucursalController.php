<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSucursalRequest;
use App\Http\Requests\UpdateSucursalRequest;
use App\Models\Sucursal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SucursalController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Sucursal::class);

        $filtros = [
            'buscar' => $request->string('buscar')->trim()->value(),
        ];

        $sucursales = Sucursal::query()
            ->withCount(['vehiculos', 'conductores'])
            ->when($filtros['buscar'], function ($query, string $buscar): void {
                $query->where(function ($query) use ($buscar): void {
                    $query->whereLike('nombre', "%{$buscar}%", caseSensitive: false)
                        ->orWhereLike('codigo', "%{$buscar}%", caseSensitive: false)
                        ->orWhereLike('ciudad', "%{$buscar}%", caseSensitive: false);
                });
            })
            ->orderBy('nombre')
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('sucursales/index', [
            'sucursales' => $sucursales,
            'filtros' => $filtros,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        $this->authorize('create', Sucursal::class);

        return Inertia::render('sucursales/create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSucursalRequest $request): RedirectResponse
    {
        $this->authorize('create', Sucursal::class);

        Sucursal::create($request->validated());

        return to_route('sucursales.index')
            ->with('toast', ['type' => 'success', 'message' => 'Sucursal registrada correctamente.']);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Sucursal $sucursal): Response
    {
        $this->authorize('update', $sucursal);

        return Inertia::render('sucursales/edit', [
            'sucursal' => $sucursal,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSucursalRequest $request, Sucursal $sucursal): RedirectResponse
    {
        $this->authorize('update', $sucursal);

        $sucursal->update($request->validated());

        return to_route('sucursales.index')
            ->with('toast', ['type' => 'success', 'message' => 'Sucursal actualizada correctamente.']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Sucursal $sucursal): RedirectResponse
    {
        $this->authorize('delete', $sucursal);

        $sucursal->loadCount(['vehiculos', 'conductores']);

        if ($sucursal->vehiculos_count > 0 || $sucursal->conductores_count > 0) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => 'No se puede eliminar: la sucursal tiene vehículos o conductores asignados.',
            ]);
        }

        $sucursal->delete();

        return to_route('sucursales.index')
            ->with('toast', ['type' => 'success', 'message' => 'Sucursal eliminada correctamente.']);
    }
}
