<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVehiculoFotoRequest;
use App\Models\Vehiculo;
use App\Models\VehiculoFoto;
use Illuminate\Http\RedirectResponse;

class VehiculoFotoController extends Controller
{
    /**
     * Store a newly uploaded photo for the given vehicle.
     */
    public function store(StoreVehiculoFotoRequest $request, Vehiculo $vehiculo): RedirectResponse
    {
        $this->authorize('update', $vehiculo);

        $foto = $vehiculo->fotos()->create([
            'posicion' => $request->validated('posicion'),
            'orden' => (int) $vehiculo->fotos()->max('orden') + 1,
        ]);

        $foto->addMediaFromRequest('archivo')->toMediaCollection('imagen');

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Foto agregada correctamente.',
        ]);
    }

    /**
     * Remove the given photo.
     */
    public function destroy(Vehiculo $vehiculo, VehiculoFoto $foto): RedirectResponse
    {
        $this->authorize('update', $vehiculo);

        $foto->delete();

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Foto eliminada.',
        ]);
    }
}
