<?php

namespace App\Http\Controllers;

use App\Enums\TipoGuia;
use App\Http\Requests\StoreViajeGuiaRequest;
use App\Models\Viaje;
use Illuminate\Http\RedirectResponse;

/**
 * El archivo de una guía de remisión: el PDF impreso o el XML de la guía
 * electrónica. El número vive en el viaje; acá solo se maneja el documento.
 */
class ViajeGuiaController extends Controller
{
    /**
     * Guarda el archivo de la guía. La colección es de un solo archivo, así que
     * volver a subir reemplaza el anterior: corregir una guía escaneada al revés
     * es subir la buena, sin borrar antes la mala.
     */
    public function store(StoreViajeGuiaRequest $request, Viaje $viaje): RedirectResponse
    {
        $guia = TipoGuia::from($request->string('tipo')->value());

        $viaje->addMediaFromRequest('archivo')->toMediaCollection($guia->coleccion());

        return back()->with('toast', [
            'type' => 'success',
            'message' => "{$guia->abreviatura()} adjuntada correctamente.",
        ]);
    }

    /**
     * Quita el archivo pero deja el número en el viaje: que se haya escaneado
     * mal el papel no significa que la guía no exista.
     */
    public function destroy(Viaje $viaje, string $tipo): RedirectResponse
    {
        $this->authorize('update', $viaje);

        $guia = TipoGuia::tryFrom($tipo);

        abort_if($guia === null, 404);

        $viaje->clearMediaCollection($guia->coleccion());

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Se quitó el archivo de la {$guia->abreviatura()}.",
        ]);
    }
}
