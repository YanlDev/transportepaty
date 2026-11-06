<?php

namespace App\Http\Controllers;

use App\Http\Requests\GuardarViaticoRequest;
use App\Models\LineaRuta;
use App\Models\Viatico;
use App\Models\Vuelta;
use App\Services\DotacionVuelta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Los viáticos de cada ticket: depositarlos (los de la ruta o un
 * imprevisto), corregirlos y anularlos. No se borran: cada movimiento queda
 * en el historial.
 */
class ViaticoController extends Controller
{
    public function __construct(private readonly DotacionVuelta $dotacion) {}

    public function store(GuardarViaticoRequest $request): RedirectResponse
    {
        $this->authorize('create', Viatico::class);

        $vuelta = Vuelta::query()->with(['paradas.viajes', 'rutas'])->findOrFail($request->integer('vuelta_id'));
        $linea = $request->filled('linea_ruta_id')
            ? LineaRuta::query()->findOrFail($request->integer('linea_ruta_id'))
            : null;

        if (($bloqueo = $vuelta->bloqueoDeGastos()) !== null) {
            throw ValidationException::withMessages(['vuelta_id' => $bloqueo]);
        }

        $previsto = $linea === null ? null : $this->asegurarQueLeToca($vuelta, $linea);

        Viatico::query()->create([
            'vuelta_id' => $vuelta->id,
            'vehiculo_id' => $vuelta->tracto_id,
            'conductor_id' => $vuelta->conductor_id,
            'linea_ruta_id' => $linea?->id,
            // Lo de la ruta como estaba al elegirla, aunque el catálogo haya
            // cambiado después.
            'concepto' => $previsto['concepto'] ?? $request->string('concepto')->trim()->toString(),
            'monto_previsto' => $previsto['monto'] ?? null,
            ...$this->datos($request),
            'user_id' => $request->user()?->id,
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => "Viático depositado en el ticket {$vuelta->codigo}."]);
    }

    public function update(GuardarViaticoRequest $request, Viatico $viatico): RedirectResponse
    {
        $this->authorize('update', $viatico);

        $viatico->update([
            ...($viatico->linea_ruta_id === null ? ['concepto' => $request->string('concepto')->trim()->toString()] : []),
            ...$this->datos($request),
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Viático corregido.']);
    }

    public function anular(Request $request, Viatico $viatico): RedirectResponse
    {
        $this->authorize('anular', $viatico);

        $datos = $request->validate(['motivo' => ['required', 'string', 'max:255']]);

        $viatico->update([
            'anulado_at' => now(),
            'anulado_por' => $request->user()?->id,
            'motivo_anulacion' => $datos['motivo'],
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => "Viático anulado: {$viatico->concepto}."]);
    }

    /**
     * La línea tiene que ser de la tarjeta del ticket y no estar depositada
     * ya (tantas veces como toque).
     */
    /**
     * La línea tiene que ser de una ruta del ticket, y no estar ya
     * depositada. Devuelve cómo estaba en la ruta al elegirla.
     *
     * @return array{linea_id: int|null, concepto: string, tipo: string, monto: float}
     */
    private function asegurarQueLeToca(Vuelta $vuelta, LineaRuta $linea): array
    {
        $deLaLinea = collect($this->dotacion->de($vuelta, $vuelta->abastecimientos()->with('punto')->get())['viaticos'])
            ->where('linea_id', $linea->id);
        $previstas = $deLaLinea->count();

        if ($previstas === 0) {
            throw ValidationException::withMessages([
                'linea_ruta_id' => "«{$linea->concepto}» no es de las rutas del ticket {$vuelta->codigo}.",
            ]);
        }

        $depositadas = $vuelta->viaticos()->vigentes()->where('linea_ruta_id', $linea->id)->count();

        if ($depositadas >= $previstas) {
            throw ValidationException::withMessages([
                'linea_ruta_id' => "«{$linea->concepto}» ya está depositado en el ticket {$vuelta->codigo}: corrígelo o anúlalo.",
            ]);
        }

        return $deLaLinea->first();
    }

    /**
     * @return array{monto: float, fecha: string, numero_operacion: string|null, observacion: string|null}
     */
    private function datos(GuardarViaticoRequest $request): array
    {
        return [
            'monto' => round($request->float('monto'), 2),
            'fecha' => $request->string('fecha')->toString(),
            'numero_operacion' => $request->string('numero_operacion')->trim()->toString() ?: null,
            'observacion' => $request->string('observacion')->trim()->toString() ?: null,
        ];
    }
}
