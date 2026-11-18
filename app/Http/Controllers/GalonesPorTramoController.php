<?php

namespace App\Http\Controllers;

use App\Enums\TipoCarga;
use App\Enums\ZonaRuta;
use App\Http\Requests\GuardarGalonesPorTramoRequest;
use App\Models\GalonesPorTramo;
use App\Models\Ruta;
use App\Services\TablaDeTramos;
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
    public function index(TablaDeTramos $tabla): Response
    {
        $this->authorize('configurar', Ruta::class);

        // Qué rutas por tramos usan cada fila (la de su carga, o la general):
        // así se ve a quién afecta.
        $usos = [];

        foreach (Ruta::query()->where('por_tramos', true)->where('activa', true)->get(['id', 'nombre', 'paradas']) as $ruta) {
            foreach ($ruta->paradas as $parada) {
                $fila = $tabla->fila($parada['zona_origen'], $parada['zona_destino'], TipoCarga::tryFrom($parada['tipo_carga']));

                if ($fila !== null) {
                    $usos[$fila->id][] = $ruta->nombre;
                }
            }
        }

        return Inertia::render('rutas/tramos', [
            'filas' => GalonesPorTramo::query()
                ->get()
                ->sortBy(fn (GalonesPorTramo $fila): string => $fila->zona_origen->label().'>'.$fila->zona_destino->label().'>'.($fila->tipo_carga?->label() ?? ''))
                ->values()
                ->map(fn (GalonesPorTramo $fila): array => [
                    'id' => $fila->id,
                    'zona_origen' => $fila->zona_origen->value,
                    'zona_destino' => $fila->zona_destino->value,
                    'tipo_carga' => $fila->tipo_carga?->value,
                    'galones_cargado' => $fila->galones_cargado === null ? null : (float) $fila->galones_cargado,
                    'galones_vacio' => $fila->galones_vacio === null ? null : (float) $fila->galones_vacio,
                    'bandas' => $fila->bandas ?? [],
                    'nota' => $fila->nota,
                    'rutas' => array_values(array_unique($usos[$fila->id] ?? [])),
                ])
                ->all(),
            'zonas' => ZonaRuta::options(),
            'cargas' => GuardarGalonesPorTramoRequest::cargas(),
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
                    'tipo_carga' => $datos['tipo_carga'] ?? null,
                ]);
                $bandas = array_values(array_map(fn (array $banda): array => [
                    'peso_desde' => isset($banda['peso_desde']) ? (float) $banda['peso_desde'] : null,
                    'peso_hasta' => isset($banda['peso_hasta']) ? (float) $banda['peso_hasta'] : null,
                    'galones' => (float) $banda['galones'],
                ], (array) ($datos['bandas'] ?? [])));
                $fila->fill([
                    // Con bandas, cargado depende del peso: el número único no se usa.
                    'galones_cargado' => $bandas === [] ? ($datos['galones_cargado'] ?? null) : null,
                    // Vacío no lleva carga: lo da la fila general.
                    'galones_vacio' => filled($datos['tipo_carga'] ?? null) ? null : ($datos['galones_vacio'] ?? null),
                    'bandas' => $bandas === [] ? null : $bandas,
                    'nota' => filled($datos['nota'] ?? null) ? trim($datos['nota']) : null,
                ])->save();
                $guardadas[] = $fila->id;
            }

            GalonesPorTramo::query()->whereNotIn('id', $guardadas)->get()->each->delete();

            // Las rutas por tramos toman sus variantes de las bandas.
            Ruta::query()->where('por_tramos', true)->get()->each->sincronizarVariantesConLaTabla();
        });

        return back()->with('toast', ['type' => 'success', 'message' => 'Galones por tramo guardados. Los tickets con rutas por tramos ya usan los números nuevos.']);
    }
}
