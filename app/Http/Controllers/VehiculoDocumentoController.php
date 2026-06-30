<?php

namespace App\Http\Controllers;

use App\Enums\TipoDocumento;
use App\Http\Requests\StoreVehiculoDocumentoRequest;
use App\Models\Vehiculo;
use App\Models\VehiculoDocumento;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class VehiculoDocumentoController extends Controller
{
    /**
     * Listado completo de documentos del vehículo.
     */
    public function index(Vehiculo $vehiculo): Response
    {
        $this->authorize('view', $vehiculo);

        $vehiculo->load('sucursal:id,nombre');

        return Inertia::render('vehiculos/documentos', [
            'vehiculo' => [
                'id' => $vehiculo->id,
                'placa' => $vehiculo->placa,
                'marca' => $vehiculo->marca,
                'modelo' => $vehiculo->modelo,
                'sucursal' => $vehiculo->sucursal?->nombre,
            ],
            'documentos' => $vehiculo->documentos()
                ->with('media')
                ->latest()
                ->get()
                ->map
                ->toFrontArray(),
            'tiposDocumento' => TipoDocumento::options(),
        ]);
    }

    /**
     * Store a newly uploaded document for the given vehicle.
     */
    public function store(StoreVehiculoDocumentoRequest $request, Vehiculo $vehiculo): RedirectResponse
    {
        $this->authorize('update', $vehiculo);

        $documento = $vehiculo->documentos()->create($request->safe()->except('archivo'));

        $documento->addMediaFromRequest('archivo')->toMediaCollection('archivo');

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Documento agregado correctamente.',
        ]);
    }

    /**
     * Remove the given document.
     */
    public function destroy(Vehiculo $vehiculo, VehiculoDocumento $documento): RedirectResponse
    {
        $this->authorize('update', $vehiculo);

        $documento->delete();

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Documento eliminado.',
        ]);
    }
}
