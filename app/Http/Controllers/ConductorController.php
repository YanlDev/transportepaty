<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreConductorRequest;
use App\Http\Requests\UpdateConductorRequest;
use App\Models\Conductor;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConductorController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Conductor::class);

        $filtros = [
            'buscar' => $request->string('buscar')->trim()->value(),
        ];

        $conductores = Conductor::query()
            ->when($filtros['buscar'], function ($query, string $buscar): void {
                $query->where(function ($query) use ($buscar): void {
                    $query->whereLike('nombres', "%{$buscar}%", caseSensitive: false)
                        ->orWhereLike('apellidos', "%{$buscar}%", caseSensitive: false)
                        ->orWhereLike('documento', "%{$buscar}%", caseSensitive: false)
                        ->orWhereLike('licencia', "%{$buscar}%", caseSensitive: false);
                });
            })
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('conductores/index', [
            'conductores' => $conductores,
            'filtros' => $filtros,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Conductor::class);

        return Inertia::render('conductores/create', $this->datosFormulario());
    }

    public function store(StoreConductorRequest $request): RedirectResponse
    {
        $this->authorize('create', Conductor::class);

        Conductor::create($request->validated());

        return to_route('conductores.index')
            ->with('toast', ['type' => 'success', 'message' => 'Conductor registrado correctamente.']);
    }

    public function edit(Conductor $conductor): Response
    {
        $this->authorize('update', $conductor);

        return Inertia::render('conductores/edit', [
            'conductor' => $conductor,
            ...$this->datosFormulario(),
        ]);
    }

    public function update(UpdateConductorRequest $request, Conductor $conductor): RedirectResponse
    {
        $this->authorize('update', $conductor);

        $conductor->update($request->validated());

        return to_route('conductores.index')
            ->with('toast', ['type' => 'success', 'message' => 'Conductor actualizado correctamente.']);
    }

    public function destroy(Conductor $conductor): RedirectResponse
    {
        $this->authorize('delete', $conductor);

        $conductor->delete();

        return to_route('conductores.index')
            ->with('toast', ['type' => 'success', 'message' => 'Conductor eliminado correctamente.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function datosFormulario(): array
    {
        return [
            'usuarios' => User::query()
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
        ];
    }
}
