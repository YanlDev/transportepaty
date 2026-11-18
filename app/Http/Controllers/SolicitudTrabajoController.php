<?php

namespace App\Http\Controllers;

use App\Enums\EstadoOrdenTrabajo;
use App\Enums\TipoOrdenTrabajo;
use App\Http\Requests\SolicitarTrabajoRequest;
use App\Models\OrdenTrabajo;
use App\Services\FileUploadService;
use App\Services\RelojOperativo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * Operaciones le pide un trabajo a taller desde la bandeja del OK de salida.
 * Nace como orden «solicitada»: no bloquea el apto ni recibe repuestos hasta
 * que taller la acepta (y la asigna) o la rechaza con su motivo.
 */
class SolicitudTrabajoController extends Controller
{
    public function store(SolicitarTrabajoRequest $request, FileUploadService $subidas): RedirectResponse
    {
        $this->authorize('solicitar', OrdenTrabajo::class);

        $orden = DB::transaction(function () use ($request, $subidas): OrdenTrabajo {
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

            return $orden;
        });

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Solicitud {$orden->numero()} enviada a taller.",
        ]);
    }
}
