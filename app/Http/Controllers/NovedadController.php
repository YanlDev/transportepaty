<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreNovedadRequest;
use App\Models\Novedad;
use Illuminate\Http\RedirectResponse;

/**
 * Registro de las novedades de campo que sacan a una unidad de la programación.
 * Se maneja desde la misma pantalla de programación, que es donde estorban.
 */
class NovedadController extends Controller
{
    public function store(StoreNovedadRequest $request): RedirectResponse
    {
        $novedad = Novedad::query()->create($request->validated());

        $novedad->load('tracto');

        return back()->with('toast', [
            'type' => 'success',
            'message' => "{$novedad->tracto->placa}: {$novedad->tipo->label()}.",
        ]);
    }

    /**
     * Levanta la novedad con la fecha de hoy. No se borra: el rastro de por qué
     * una unidad no subió tal día tiene que quedar.
     */
    public function levantar(Novedad $novedad): RedirectResponse
    {
        $this->authorize('update', $novedad);

        $novedad->levantar(now()->toDateString());

        $novedad->load('tracto');

        return back()->with('toast', [
            'type' => 'success',
            'message' => "{$novedad->tracto->placa} vuelve a entrar en la programación.",
        ]);
    }
}
