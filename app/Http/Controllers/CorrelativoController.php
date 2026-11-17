<?php

namespace App\Http\Controllers;

use App\Enums\Permiso;
use App\Models\Factura;
use App\Models\NumeroDeFacturaExplicado;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Explicar los números de factura que faltan en el correlativo: una vez
 * dicho por qué no están (se anuló en SUNAT, no era un flete), dejan de
 * avisarse. No crea ni toca ninguna factura.
 */
class CorrelativoController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->checkPermissionTo(Permiso::CobranzaGestionar) ?? false, 403);

        $datos = $request->validate([
            'serie' => ['required', 'string', 'max:10', 'regex:/^[A-Za-z0-9]+$/'],
            'desde' => ['required', 'integer', 'min:1'],
            'hasta' => ['required', 'integer', 'gte:desde'],
            'motivo' => ['required', Rule::in(array_keys(NumeroDeFacturaExplicado::MOTIVOS))],
            'nota' => ['nullable', 'string', 'max:255', 'required_if:motivo,otro'],
        ], [
            'nota.required_if' => 'Con «Otro», escribe qué pasó.',
        ]);

        if ($datos['hasta'] - $datos['desde'] >= 200) {
            return back()->withErrors(['hasta' => 'Son demasiados números de una vez: explícalos en tramos de hasta 200.']);
        }

        $serie = mb_strtoupper($datos['serie']);
        $explicados = 0;

        foreach (range((int) $datos['desde'], (int) $datos['hasta']) as $numero) {
            // Si la factura está en el sistema, no falta: no hay nada que explicar.
            if (Factura::conMismoNumero("{$serie}-{$numero}") !== null) {
                continue;
            }

            NumeroDeFacturaExplicado::query()->updateOrCreate(
                ['serie' => $serie, 'numero' => $numero],
                ['motivo' => $datos['motivo'], 'nota' => $datos['nota'] ?? null, 'user_id' => $request->user()->id],
            );
            $explicados++;
        }

        return back()->with('toast', [
            'type' => 'success',
            'message' => $explicados === 1 ? '1 número explicado.' : "{$explicados} números explicados.",
        ]);
    }
}
