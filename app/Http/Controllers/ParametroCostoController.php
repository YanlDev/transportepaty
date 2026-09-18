<?php

namespace App\Http\Controllers;

use App\Enums\MetodoCosto;
use App\Enums\NaturalezaCosto;
use App\Enums\TipoComponente;
use App\Http\Requests\StoreComponenteCostoRequest;
use App\Http\Requests\UpdateComponenteCostoRequest;
use App\Http\Requests\UpdateParametroFlotaRequest;
use App\Models\ComponenteCosto;
use App\Models\ParametroFlota;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El tarifario de la casa: qué se paga, por día o por kilómetro, y cuánto.
 *
 * Las tasas se escriben a mano, como en la hoja de cotización. Las líneas que
 * tienen una cuenta detrás (planilla, depreciación, diésel) la muestran como
 * calculadora de apoyo, para poder abrir «mano de obra directa» y ver de dónde
 * saldrían esos soles antes de decidir el número que se cotiza.
 */
class ParametroCostoController extends Controller
{
    public function edit(): Response
    {
        $this->authorize('update', ParametroFlota::class);

        $flota = ParametroFlota::vigentes();
        $componentes = ComponenteCosto::query()->ordenados()->get();

        return Inertia::render('parametros-costo/edit', [
            'flota' => [
                ...$flota->only([
                    'tamano_flota', 'dias_ano', 'dias_mantenimiento',
                    'dias_certificaciones', 'dias_sincronizacion', 'igv_pct',
                    'margen_pct_default',
                ]),
                'dias_disponibles' => round($flota->diasDisponibles(), 2),
            ],
            'componentes' => $componentes
                ->map(function (ComponenteCosto $componente) use ($flota): array {
                    $derivacion = $componente->derivacion($flota);

                    return [
                        'id' => $componente->id,
                        'nombre' => $componente->nombre,
                        'tipo' => $componente->tipo->value,
                        'unidad' => $componente->tipo->unidad(),
                        'metodo' => $componente->metodo->value,
                        'metodo_label' => $componente->metodo->label(),
                        'entradas' => $componente->entradas,
                        'activo' => $componente->activo,
                        'tasa' => $componente->tasa,
                        'tiene_calculadora' => $componente->tieneCalculadora(),
                        'tasa_calculada' => $derivacion->tasa,
                        'pasos' => $derivacion->pasos,
                    ];
                })
                ->all(),
            'totales' => $this->totales($componentes),
            'tipos' => TipoComponente::options(),
        ]);
    }

    public function update(UpdateParametroFlotaRequest $request): RedirectResponse
    {
        $this->authorize('update', ParametroFlota::class);

        ParametroFlota::vigentes()->update($request->validated());

        return back()->with('toast', [
            'type' => 'success',
            // Las cotizaciones ya emitidas guardan el desglose con el que se
            // calcularon, así que esto no les cambia el precio.
            'message' => 'Parámetros de flota actualizados. Las cotizaciones ya emitidas mantienen su tarifa.',
        ]);
    }

    public function storeComponente(StoreComponenteCostoRequest $request): RedirectResponse
    {
        $this->authorize('update', ParametroFlota::class);

        $componente = ComponenteCosto::create([
            ...$request->validated(),
            'naturaleza' => NaturalezaCosto::Directo,
            'metodo' => MetodoCosto::Manual,
            'entradas' => [],
            'orden' => (int) ComponenteCosto::query()->max('orden') + 1,
            'activo' => true,
        ]);

        return back()->with('toast', [
            'type' => 'success',
            'message' => "«{$componente->nombre}» agregado al tarifario.",
        ]);
    }

    public function updateComponente(UpdateComponenteCostoRequest $request, ComponenteCosto $componente): RedirectResponse
    {
        $this->authorize('update', ParametroFlota::class);

        $componente->update($request->validated());

        return back()->with('toast', [
            'type' => 'success',
            'message' => "«{$componente->nombre}» actualizado.",
        ]);
    }

    /**
     * Lo que suma el tarifario vigente, que es el número de la hoja: tanto por
     * día que la unidad queda tomada, tanto por kilómetro rodado.
     *
     * @param  Collection<int, ComponenteCosto>  $componentes
     * @return array{fijo_dia: float, variable_km: float}
     */
    private function totales(Collection $componentes): array
    {
        $activos = $componentes->where('activo', true);

        return [
            'fijo_dia' => round($activos->where('tipo', TipoComponente::FijoDia)->sum('tasa'), 4),
            'variable_km' => round($activos->where('tipo', TipoComponente::VariableKm)->sum('tasa'), 4),
        ];
    }
}
