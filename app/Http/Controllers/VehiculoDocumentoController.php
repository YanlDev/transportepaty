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

        $vehiculo->load('documentos.media');

        return Inertia::render('vehiculos/documentos', [
            'vehiculo' => [
                'id' => $vehiculo->id,
                'placa' => $vehiculo->placa,
                'marca' => $vehiculo->marca,
                'modelo' => $vehiculo->modelo,
                'tipo' => $vehiculo->tipo->value,
                'tipo_label' => $vehiculo->tipo->label(),
            ],
            'ranuras' => $vehiculo->ranurasDocumentales(),
            // El SOAT solo aplica a tractos, así que la carreta no debe ofrecerlo.
            'tiposDocumento' => array_map(
                fn (TipoDocumento $tipo): array => [
                    'value' => $tipo->value,
                    'label' => $tipo->label(),
                ],
                $vehiculo->tipo->documentosAplicables(),
            ),
        ]);
    }

    /**
     * Guarda el documento del vehículo. Como solo puede haber uno de cada tipo,
     * si ya existe se actualiza y se reemplaza su archivo en vez de fallar
     * contra el índice único: renovar un SOAT es subir el nuevo, sin borrar
     * antes el viejo.
     */
    public function store(StoreVehiculoDocumentoRequest $request, Vehiculo $vehiculo): RedirectResponse
    {
        $this->authorize('update', $vehiculo);

        $datos = $request->safe()->except('archivo');

        $documento = $vehiculo->documentos()->updateOrCreate(
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
