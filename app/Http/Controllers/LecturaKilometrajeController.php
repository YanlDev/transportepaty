<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLecturaKilometrajeRequest;
use App\Models\LecturaKilometraje;
use App\Models\Vehiculo;
use App\Services\ProximoMantenimiento;
use Illuminate\Http\RedirectResponse;

/**
 * Anotar el km de una unidad al llegar a base, desde el celular. El aviso de
 * vuelta dice ya lo que importa: cuánto le falta para su próximo servicio.
 */
class LecturaKilometrajeController extends Controller
{
    public function store(StoreLecturaKilometrajeRequest $request, ProximoMantenimiento $mantenimiento): RedirectResponse
    {
        $this->authorize('create', LecturaKilometraje::class);

        $lectura = LecturaKilometraje::create([
            ...$request->safe()->except('foto'),
            'leido_en' => now(),
            'user_id' => $request->user()?->id,
        ]);

        if ($request->hasFile('foto')) {
            $lectura->addMediaFromRequest('foto')->toMediaCollection('foto');
        }

        $vehiculo = Vehiculo::query()->findOrFail($lectura->vehiculo_id);
        $proximo = $mantenimiento->paraVehiculo($vehiculo)['proximo'];

        $mensaje = "Km de {$vehiculo->placa} registrado.";

        if ($proximo !== null && $proximo['faltan_km'] !== null) {
            $mensaje .= $proximo['faltan_km'] > 0
                ? ' Faltan '.number_format($proximo['faltan_km'], 0, '.', ',')." km para el {$proximo['nombre']}."
                : " El {$proximo['nombre']} está vencido por ".number_format(abs($proximo['faltan_km']), 0, '.', ',').' km.';
        }

        return back()->with('toast', [
            'type' => $proximo !== null && ($proximo['faltan_km'] ?? 1) <= 0 ? 'warning' : 'success',
            'message' => $mensaje,
        ]);
    }

    public function destroy(LecturaKilometraje $lectura): RedirectResponse
    {
        $this->authorize('delete', $lectura);

        $lectura->delete();

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Lectura de km eliminada.',
        ]);
    }
}
