<?php

namespace App\Http\Controllers;

use App\Models\HistorialCambio;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El historial de movimientos del sistema: quién creó, cambió, anuló o
 * eliminó qué, cuándo, y con qué valores antes y después.
 */
class HistorialController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', HistorialCambio::class);

        $filtros = $request->validate([
            'modelo' => ['nullable', 'string', 'max:60'],
            'accion' => ['nullable', 'in:creado,editado,anulado,eliminado'],
            'usuario' => ['nullable', 'integer'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d'],
            'buscar' => ['nullable', 'string', 'max:100'],
        ]);

        $movimientos = HistorialCambio::query()
            ->with('usuario:id,name,username')
            ->when($filtros['modelo'] ?? null, fn ($query, string $modelo) => $query->where('modelo', $modelo))
            ->when($filtros['accion'] ?? null, fn ($query, string $accion) => $query->where('accion', $accion))
            ->when($filtros['usuario'] ?? null, fn ($query, int|string $usuario) => $query->where('user_id', $usuario))
            ->when($filtros['desde'] ?? null, fn ($query, string $desde) => $query->whereDate('created_at', '>=', $desde))
            ->when($filtros['hasta'] ?? null, fn ($query, string $hasta) => $query->whereDate('created_at', '<=', $hasta))
            ->when($filtros['buscar'] ?? null, fn ($query, string $buscar) => $query->whereLike('etiqueta', "%{$buscar}%", caseSensitive: false))
            ->latest('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (HistorialCambio $movimiento): array => [
                'id' => $movimiento->id,
                'fecha' => $movimiento->created_at->toIso8601String(),
                'usuario' => $movimiento->usuario?->name,
                'accion' => $movimiento->accion,
                'modelo' => $movimiento->modelo,
                'modelo_id' => $movimiento->modelo_id,
                'etiqueta' => $movimiento->etiqueta,
                'cambios' => $movimiento->cambios ?? [],
            ]);

        return Inertia::render('historial/index', [
            'movimientos' => $movimientos,
            'filtros' => [
                'modelo' => $filtros['modelo'] ?? '',
                'accion' => $filtros['accion'] ?? '',
                'usuario' => isset($filtros['usuario']) ? (string) $filtros['usuario'] : '',
                'desde' => $filtros['desde'] ?? '',
                'hasta' => $filtros['hasta'] ?? '',
                'buscar' => $filtros['buscar'] ?? '',
            ],
            'modelos' => fn (): array => HistorialCambio::query()->distinct()->orderBy('modelo')->pluck('modelo')->all(),
            'usuarios' => fn (): array => User::query()->orderBy('name')->get(['id', 'name'])->map(fn (User $usuario): array => ['id' => $usuario->id, 'name' => $usuario->name])->all(),
        ]);
    }
}
