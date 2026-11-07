<?php

namespace App\Http\Controllers;

use App\Enums\SentidoTramo;
use App\Http\Requests\GuardarAdicionalRequest;
use App\Models\RutaVuelta;
use App\Models\Vuelta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

/**
 * Los adicionales del ticket: galones extra en un tramo, con su motivo, solo
 * para este viaje. Suman al tope como una ruta más; se quitan mientras el
 * tramo no tenga combustible autorizado.
 */
class AdicionalTramoController extends Controller
{
    public function store(GuardarAdicionalRequest $request, Vuelta $vuelta): RedirectResponse
    {
        $vuelta->rutas()->create([
            ...RutaVuelta::adicional($request->float('galones'), $request->string('motivo')->squish()->toString()),
            'sentido' => $request->enum('sentido', SentidoTramo::class),
            'orden' => (int) $vuelta->rutas()->max('orden') + 1,
            'user_id' => $request->user()?->id,
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Adicional agregado: el tope del tramo sube.']);
    }

    public function destroy(Vuelta $vuelta, RutaVuelta $adicional): RedirectResponse
    {
        $this->authorize('update', $vuelta);

        abort_unless($adicional->vuelta_id === $vuelta->id && $adicional->esAdicional(), 404);

        if ($vuelta->tramoConGastos($adicional->sentido)) {
            throw ValidationException::withMessages([
                'adicional' => 'El tramo ya tiene combustible autorizado con este adicional: no se quita. Anula ese combustible antes.',
            ]);
        }

        $adicional->delete();

        return back()->with('toast', ['type' => 'success', 'message' => 'Adicional quitado.']);
    }
}
