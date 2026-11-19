<?php

namespace App\Http\Controllers;

use App\Enums\EstadoOrdenTrabajo;
use App\Enums\TipoOrdenTrabajo;
use App\Http\Requests\SolicitarTrabajoRequest;
use App\Models\OrdenTrabajo;
use App\Services\EjecucionDeOrden;
use App\Services\FileUploadService;
use App\Services\RelojOperativo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * Operaciones crea la pre-orden desde la bandeja del OK de salida y elige al
 * mecánico. Desde ese momento la unidad está en taller (no recibe el apto);
 * el mecánico arma el requerimiento y operaciones lo aprueba.
 */
class SolicitudTrabajoController extends Controller
{
    public function store(SolicitarTrabajoRequest $request, FileUploadService $subidas, EjecucionDeOrden $ejecucion): RedirectResponse
    {
        $this->authorize('solicitar', OrdenTrabajo::class);

        $orden = DB::transaction(function () use ($request, $subidas, $ejecucion): OrdenTrabajo {
            $orden = OrdenTrabajo::create([
                'vehiculo_id' => $request->integer('vehiculo_id'),
                'tipo' => TipoOrdenTrabajo::Correctiva,
                'estado' => EstadoOrdenTrabajo::Solicitada,
                'prioridad' => $request->validated('prioridad'),
                'fecha' => RelojOperativo::hoy(),
                'trabajo' => $request->validated('trabajo'),
                'user_id' => $request->user()?->id,
            ]);

            foreach ($request->file('fotos', []) as $indice => $foto) {
                $subidas->guardar($orden, $foto, 'antes', "fotos.{$indice}");
            }

            $ejecucion->asignar(
                $orden,
                array_map(intval(...), $request->validated('mecanicos')),
                $request->integer('responsable_id') ?: null,
            );

            return $orden;
        });

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Pre-orden {$orden->numero()} creada: el mecánico arma el requerimiento.",
        ]);
    }
}
