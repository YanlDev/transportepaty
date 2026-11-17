<?php

namespace App\Http\Controllers;

use App\Models\Factura;
use App\Services\ReporteGerencia;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El resumen de la cobranza para la gerencia: una sola pantalla, pensada
 * para el celular, con lo que falta facturar, lo que falta cobrar, lo que ya
 * entró y las detracciones que le toca depositar a la empresa.
 */
class ReporteCobranzaController extends Controller
{
    public function index(Request $request, ReporteGerencia $reporte): Response
    {
        $this->authorize('viewAny', Factura::class);

        // Llega por la URL: solo vale con la forma `AAAA-MM`.
        $mes = $request->string('mes')->toString();
        $mes = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes) === 1 ? $mes : null;

        return Inertia::render('contabilidad/reporte', [
            'reporte' => $reporte->de($mes),
        ]);
    }
}
