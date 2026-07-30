<?php

namespace App\Http\Controllers;

use App\Enums\TipoDocumentoConductor;
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
            // Una sola carga alimenta el semáforo documental de cada fila.
            ->with(['documentos:id,conductor_id,tipo,numero,fecha_vencimiento'])
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
            ->withQueryString()
            ->through(fn (Conductor $conductor): array => [
                'id' => $conductor->id,
                'nombres' => $conductor->nombres,
                'apellidos' => $conductor->apellidos,
                'nombre_completo' => $conductor->nombre_completo,
                'documento' => $conductor->documento,
                'licencia' => $conductor->licencia,
                'categoria_licencia' => $conductor->categoria_licencia,
                'licencia_vence' => $conductor->licencia_vence?->toDateString(),
                'telefono' => $conductor->telefono,
                'email' => $conductor->email,
                'procedencia' => $conductor->procedencia,
                'activo' => $conductor->activo,
                'documentacion' => $conductor->estadoDocumental(),
            ]);

        return Inertia::render('conductores/index', [
            'conductores' => $conductores,
            'filtros' => $filtros,
        ]);
    }

    public function show(Conductor $conductor): Response
    {
        $this->authorize('view', $conductor);

        $conductor->load('documentos.media');

        return Inertia::render('conductores/show', [
            'conductor' => $conductor,
            'documentacion' => $conductor->estadoDocumental(),
            'ranuras' => $conductor->ranurasDocumentales(),
            'tiposDocumento' => TipoDocumentoConductor::options(),
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

        // Borrar al conductor arrastraría sus asignaciones (cascade) y con
        // ellas el historial de quién manejó qué unidad. Al que ya trabajó se
        // le marca inactivo; borrar queda solo para registros equivocados.
        if ($conductor->asignaciones()->exists()) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => "{$conductor->nombre_completo} tiene historial de asignaciones. Márcalo como inactivo en lugar de eliminarlo.",
            ]);
        }

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
