<?php

namespace App\Http\Controllers;

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
