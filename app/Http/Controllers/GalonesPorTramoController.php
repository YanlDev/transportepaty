<?php

namespace App\Http\Controllers;

use App\Enums\ZonaRuta;
use App\Http\Requests\GuardarGalonesPorTramoRequest;
use App\Models\GalonesPorTramo;
use App\Models\Ruta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La tabla de galones por tramo: cuánto gasta la unidad de una zona a otra,
 * cargada y vacía. Las rutas por tramos suman de aquí, y los tickets con
 * esas rutas también: cambiar un número cambia el tope de sus tramos.
 */
class GalonesPorTramoController extends Controller
{
    public function index(): Response
    {
        $this->authorize('configurar', Ruta::class);

        // Qué rutas por tramos usan cada tramo: así se ve a quién afecta.
        $usos = [];

        foreach (Ruta::query()->where('por_tramos', true)->where('activa', true)->get(['id', 'nombre', 'paradas']) as $ruta) {
            foreach ($ruta->paradas as $parada) {
                $usos[$parada['zona_origen'].'>'.($parada['zona_destino'] ?? '')][] = $ruta->nombre;
            }
        }

        return Inertia::render('rutas/tramos', [
            'filas' => GalonesPorTramo::query()
                ->get()
                ->sortBy(fn (GalonesPorTramo $fila): string => $fila->zona_origen->label().'>'.$fila->zona_destino->label())
                ->values()
                ->map(fn (GalonesPorTramo $fila): array => [
                    'id' => $fila->id,
                    'zona_origen' => $fila->zona_origen->value,
                    'zona_destino' => $fila->zona_destino->value,
                    'galones_cargado' => $fila->galones_cargado === null ? null : (float) $fila->galones_cargado,
                    'galones_vacio' => $fila->galones_vacio === null ? null : (float) $fila->galones_vacio,
                    'nota' => $fila->nota,
                    'rutas' => array_values(array_unique($usos[$fila->zona_origen->value.'>'.$fila->zona_destino->value] ?? [])),
                ])
                ->all(),
            'zonas' => ZonaRuta::options(),
        ]);
    }

    public function guardar(GuardarGalonesPorTramoRequest $request): RedirectResponse
    {
        $this->authorize('configurar', Ruta::class);

        DB::transaction(function () use ($request): void {
            $guardadas = [];

            foreach ((array) $request->validated('filas', []) as $datos) {
                $fila = GalonesPorTramo::query()->firstOrNew([
                    'zona_origen' => $datos['zona_origen'],
                    'zona_destino' => $datos['zona_destino'],
                ]);
                $fila->fill([
                    'galones_cargado' => $datos['galones_cargado'] ?? null,
                    'galones_vacio' => $datos['galones_vacio'] ?? null,
                    'nota' => filled($datos['nota'] ?? null) ? trim($datos['nota']) : null,
                ])->save();
                $guardadas[] = $fila->id;
            }

            GalonesPorTramo::query()->whereNotIn('id', $guardadas)->get()->each->delete();
        });

        return back()->with('toast', ['type' => 'success', 'message' => 'Galones por tramo guardados. Los tickets con rutas por tramos ya usan los números nuevos.']);
    }
}
