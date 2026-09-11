<?php

namespace App\Http\Controllers;

use App\Http\Requests\ActualizarVencimientoRequest;
use App\Http\Requests\StoreConductorDocumentoRequest;
use App\Models\Conductor;
use App\Models\ConductorDocumento;
use Illuminate\Http\RedirectResponse;

class ConductorDocumentoController extends Controller
{
    /**
     * Guarda el documento del conductor. Como solo puede haber uno de cada
     * tipo, si ya existe se actualiza y se reemplaza su archivo en vez de
     * crear un duplicado.
     */
    public function store(StoreConductorDocumentoRequest $request, Conductor $conductor): RedirectResponse
    {
        $this->authorize('update', $conductor);

        $datos = $request->safe()->except('archivo');

        $documento = $conductor->documentos()->updateOrCreate(
            ['tipo' => $datos['tipo']],
            $datos,
        );

        // La colección es `singleFile`, así que esto descarta el archivo previo.
        $documento->addMediaFromRequest('archivo')->toMediaCollection('archivo');

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Documento guardado correctamente.',
        ]);
    }

    /**
     * Corrige el vencimiento sin volver a subir el archivo.
     *
     * Es la edición que más se pide en el expediente: el documento escaneado
     * está bien, lo que se cargó mal —o se renovó— es la fecha. Reemplazar el
     * archivo entero para corregir un día era el único camino que había.
     */
    public function actualizarVencimiento(
        ActualizarVencimientoRequest $request,
        Conductor $conductor,
        ConductorDocumento $documento,
    ): RedirectResponse {
        $this->authorize('update', $conductor);

        $documento->update([
            'fecha_vencimiento' => $request->validated('fecha_vencimiento'),
        ]);

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Vencimiento actualizado.',
        ]);
    }

    /**
     * Remove the given document.
     */
    public function destroy(Conductor $conductor, ConductorDocumento $documento): RedirectResponse
    {
        $this->authorize('update', $conductor);

        $documento->delete();

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Documento eliminado.',
        ]);
    }
}
