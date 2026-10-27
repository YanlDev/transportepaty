<?php

namespace App\Http\Controllers;

use App\Http\Requests\GuardarViaticoRequest;
use App\Models\LineaReglaAbastecimiento;
use App\Models\Viatico;
use App\Models\Vuelta;
use App\Services\DotacionVuelta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Los viáticos de cada ticket: depositarlos (los de la pizarra o un
 * imprevisto), corregirlos y anularlos. No se borran: cada movimiento queda
 * en el historial.
 */
class ViaticoController extends Controller
{
    public function __construct(private readonly DotacionVuelta $dotacion) {}

    public function store(GuardarViaticoRequest $request): RedirectResponse
    {
        $this->authorize('create', Viatico::class);

        $vuelta = Vuelta::query()->with('paradas.viajes')->findOrFail($request->integer('vuelta_id'));
        $linea = $request->filled('linea_regla_abastecimiento_id')
            ? LineaReglaAbastecimiento::query()->findOrFail($request->integer('linea_regla_abastecimiento_id'))
            : null;

        // Como el combustible: sin el OK de taller el ticket no avanza.
        if (! $vuelta->tieneOkDeTaller()) {
            throw ValidationException::withMessages([
                'vuelta_id' => "El ticket {$vuelta->codigo} todavía no tiene el OK de taller.",
            ]);
        }

        if ($linea !== null) {
            $this->asegurarQueLeToca($vuelta, $linea);
        }

        Viatico::query()->create([
            'vuelta_id' => $vuelta->id,
            'vehiculo_id' => $vuelta->tracto_id,
            'conductor_id' => $vuelta->conductor_id,
            'linea_regla_abastecimiento_id' => $linea?->id,
            'concepto' => $linea->concepto ?? $request->string('concepto')->trim()->toString(),
            'monto_previsto' => $linea?->monto,
            ...$this->datos($request),
            'user_id' => $request->user()?->id,
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => "Viático depositado en el ticket {$vuelta->codigo}."]);
    }

    public function update(GuardarViaticoRequest $request, Viatico $viatico): RedirectResponse
    {
        $this->authorize('update', $viatico);

        $viatico->update([
            ...($viatico->linea_regla_abastecimiento_id === null ? ['concepto' => $request->string('concepto')->trim()->toString()] : []),
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
    private function asegurarQueLeToca(Vuelta $vuelta, LineaReglaAbastecimiento $linea): void
    {
        $previstas = collect($this->dotacion->de($vuelta, $vuelta->abastecimientos()->get())['viaticos'])
            ->where('linea_id', $linea->id)
            ->count();

        if ($previstas === 0) {
            throw ValidationException::withMessages([
                'linea_regla_abastecimiento_id' => "«{$linea->concepto}» no es de la tarjeta del ticket {$vuelta->codigo}.",
            ]);
        }

        $depositadas = $vuelta->viaticos()->vigentes()->where('linea_regla_abastecimiento_id', $linea->id)->count();

        if ($depositadas >= $previstas) {
            throw ValidationException::withMessages([
                'linea_regla_abastecimiento_id' => "«{$linea->concepto}» ya está depositado en el ticket {$vuelta->codigo}: corrígelo o anúlalo.",
            ]);
        }
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
