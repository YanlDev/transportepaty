<?php

namespace App\Http\Controllers;

use App\Enums\MetodoCosto;
use App\Enums\NaturalezaCosto;
use App\Enums\TipoComponente;
use App\Http\Requests\UpdateComponenteCostoRequest;
use App\Http\Requests\UpdateParametroFlotaRequest;
use App\Models\ComponenteCosto;
use App\Models\Cotizacion;
use App\Models\ParametroFlota;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La estructura de costos de la casa: qué se paga, por día o por kilómetro, y
 * de dónde sale cada tasa.
 *
 * No es una pantalla de configuración cualquiera —es el Excel de costos, con
 * la cadena que deriva cada número a la vista— porque una tarifa se discute, y
 * poder abrir «mano de obra directa» y ver de dónde salen esos soles es lo que
 * separa un precio defendible de uno que hay que creer.
 */
class ParametroCostoController extends Controller
{
    public function edit(): Response
    {
        $this->authorize('create', Cotizacion::class);

        $flota = ParametroFlota::vigentes();
        $componentes = ComponenteCosto::query()->ordenados()->get();

        return Inertia::render('parametros-costo/edit', [
            'flota' => [
                ...$flota->only([
                    'tamano_flota', 'dias_ano', 'dias_mantenimiento',
                    'dias_certificaciones', 'dias_sincronizacion', 'igv_pct',
                    'margen_pct_default', 'viatico_dia',
                ]),
                'dias_disponibles' => round($flota->diasDisponibles(), 2),
            ],
            'componentes' => $componentes
                ->map(fn (ComponenteCosto $componente): array => [
                    'id' => $componente->id,
                    'nombre' => $componente->nombre,
                    'tipo' => $componente->tipo->value,
                    'unidad' => $componente->tipo->unidad(),
                    'naturaleza' => $componente->naturaleza->value,
                    'metodo' => $componente->metodo->value,
                    'metodo_label' => $componente->metodo->label(),
                    'entradas' => $componente->entradas,
                    'activo' => $componente->activo,
                    ...$componente->derivacion($flota)->toArray(),
                ])
                ->all(),
            'totales' => $this->totales($componentes, $flota),
            'tipos' => TipoComponente::options(),
            'naturalezas' => NaturalezaCosto::options(),
            'metodos' => MetodoCosto::options(),
        ]);
    }

    public function update(UpdateParametroFlotaRequest $request): RedirectResponse
    {
        $this->authorize('create', Cotizacion::class);

        ParametroFlota::vigentes()->update($request->validated());

        return back()->with('toast', [
            'type' => 'success',
            // Las cotizaciones ya emitidas guardan el desglose con el que se
            // calcularon, así que esto no les cambia el precio.
            'message' => 'Parámetros de flota actualizados. Las cotizaciones ya emitidas mantienen su tarifa.',
        ]);
    }

    public function updateComponente(UpdateComponenteCostoRequest $request, ComponenteCosto $componente): RedirectResponse
    {
        $this->authorize('create', Cotizacion::class);

        $componente->update($request->validated());

        return back()->with('toast', [
            'type' => 'success',
            'message' => "«{$componente->nombre}» actualizado.",
        ]);
    }

    /**
     * Lo que suma la estructura, que es el número con el que se compara contra
     * el Excel de la casa: tanto por día parado, tanto por kilómetro rodado.
     *
     * @param  Collection<int, ComponenteCosto>  $componentes
     * @return array<string, float>
     */
    private function totales(Collection $componentes, ParametroFlota $flota): array
    {
        $activos = $componentes->where('activo', true);

        $suma = fn (TipoComponente $tipo, ?NaturalezaCosto $naturaleza = null): float => round(
            $activos
                ->where('tipo', $tipo)
                ->when($naturaleza !== null, fn ($lineas) => $lineas->where('naturaleza', $naturaleza))
                ->sum(fn (ComponenteCosto $componente): float => $componente->tasa($flota)),
            4,
        );

        return [
            'fijo_dia' => $suma(TipoComponente::FijoDia),
            'fijo_dia_directo' => $suma(TipoComponente::FijoDia, NaturalezaCosto::Directo),
            'fijo_dia_indirecto' => $suma(TipoComponente::FijoDia, NaturalezaCosto::Indirecto),
            'variable_km' => $suma(TipoComponente::VariableKm),
            'variable_km_directo' => $suma(TipoComponente::VariableKm, NaturalezaCosto::Directo),
            'variable_km_indirecto' => $suma(TipoComponente::VariableKm, NaturalezaCosto::Indirecto),
        ];
    }
}
