<?php

namespace App\Http\Controllers;

use App\Services\BuscadorGlobal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BuscadorGlobalController extends Controller
{
    /**
     * Lo que encuentra el buscador de la barra (Ctrl+K), ya filtrado por lo
     * que la persona puede ver.
     */
    public function __invoke(Request $request, BuscadorGlobal $buscador): JsonResponse
    {
        $datos = $request->validate(['q' => ['nullable', 'string', 'max:60']]);

        return response()->json([
            'grupos' => $buscador->buscar($request->user(), (string) ($datos['q'] ?? '')),
        ]);
    }
}
