<?php

namespace App\Http\Controllers;

use App\Models\Ubigeo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Buscador de distritos para el selector de ubigeo.
 *
 * Son 1.874 distritos: demasiados para mandarlos todos al navegador con cada
 * carga de página, y pocos para justificar un índice aparte. Se consultan al
 * escribir.
 */
class UbigeoController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $termino = $request->string('buscar')->trim()->value();

        if (mb_strlen($termino) < 2) {
            return response()->json([]);
        }

        return response()->json(
            Ubigeo::query()
                ->buscar($termino)
                ->orderBy('busqueda')
                ->limit(15)
                ->get()
                ->map(fn (Ubigeo $ubigeo): array => [
                    'codigo' => $ubigeo->codigo,
                    'etiqueta' => $ubigeo->etiqueta(),
                ])
                ->all()
        );
    }
}
