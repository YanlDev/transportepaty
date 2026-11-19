<?php

namespace App\Http\Controllers;

use App\Enums\NivelMantenimiento;
use App\Enums\TipoVehiculo;
use App\Enums\UnidadMedida;
use App\Http\Requests\UpdatePlanMantenimientoRequest;
use App\Models\Articulo;
use App\Models\OrdenTrabajo;
use App\Models\PlanMantenimiento;
use App\Models\RepuestoServicio;
use App\Models\ServicioMantenimiento;
use App\Models\Vehiculo;
use App\Services\ArticulosDelPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El plan preventivo de cada marca, configurable: el plan del fabricante es
 * el punto de partida, y el taller lo ajusta a lo que de verdad se hace.
 * Nada se recalcula al guardar: el semáforo sale del plan cada vez que se
 * mira (ver `ProximoMantenimiento`).
 */
class PlanMantenimientoController extends Controller
{
    public function edit(PlanMantenimiento $plan): Response
    {
        $this->authorize('update', $plan);

        $plan->load('servicios.repuestos');

        return Inertia::render('mantenimiento/plan', [
            'plan' => [
                'id' => $plan->id,
                'marca' => $plan->marca,
                'nombre' => $plan->nombre,
                'logo_url' => $plan->logoUrl(),
                'unidades' => Vehiculo::query()
                    ->where('tipo', TipoVehiculo::Tracto)
                    ->whereRaw('upper(trim(marca)) = ?', [$plan->marca])
                    ->count(),
                'servicios' => $plan->servicios
                    ->map(fn (ServicioMantenimiento $servicio): array => [
                        'codigo' => $servicio->codigo,
                        'nombre' => $servicio->nombre,
                        'intervalo_km' => $servicio->intervalo_km,
                        'intervalo_meses' => $servicio->intervalo_meses,
                        'ordenes' => OrdenTrabajo::query()->where('servicio_mantenimiento_id', $servicio->id)->count(),
                        'repuestos' => $servicio->repuestos
                            ->map(fn (RepuestoServicio $repuesto): array => [
                                'descripcion' => $repuesto->descripcion,
                                'codigo' => $repuesto->codigo,
                                'cantidad' => $repuesto->cantidad === null ? null : (float) $repuesto->cantidad,
                                'unidad' => ArticulosDelPlan::unidad($repuesto->unidad)->value,
                                'articulo_id' => $repuesto->articulo_id,
                                'es_servicio' => $repuesto->es_servicio,
                            ])
                            ->values()
                            ->all(),
                    ])
                    ->values()
                    ->all(),
            ],
            'niveles' => NivelMantenimiento::options(),
            'unidades' => UnidadMedida::options(),
            // El catálogo del almacén, para ligar cada ítem a su artículo.
            'articulos' => Articulo::query()
                ->orderBy('nombre')
                ->get()
                ->map(fn (Articulo $articulo): array => [
                    'id' => $articulo->id,
                    'codigo' => $articulo->codigo,
                    'nombre' => $articulo->nombre,
                    'numero_parte' => $articulo->numero_parte,
                    'unidad' => $articulo->unidad->value,
                ])
                ->values()
                ->all(),
        ]);
    }

    /**
     * Guarda el plan entero. Cada nivel conserva su fila —y con ella las
     * órdenes que apuntan a él—; sus ítems se reemplazan por los que llegan.
     */
    public function update(UpdatePlanMantenimientoRequest $request, PlanMantenimiento $plan, ArticulosDelPlan $articulos): RedirectResponse
    {
        $this->authorize('update', $plan);

        /** @var list<array{codigo: string, nombre: string, intervalo_km: int, intervalo_meses: int|null, repuestos: list<array{descripcion: string, codigo: string|null, cantidad: float|null, unidad: string|null, articulo_id?: int|null, es_servicio?: bool|null}>}> $servicios */
        $servicios = $request->validated('servicios');

        DB::transaction(function () use ($request, $plan, $servicios, $articulos): void {
            $plan->update(['nombre' => $request->validated('nombre')]);

            $plan->servicios()->whereNotIn('codigo', array_column($servicios, 'codigo'))->delete();

            foreach ($servicios as $datos) {
                $servicio = $plan->servicios()->updateOrCreate(
                    ['codigo' => $datos['codigo']],
                    [
                        'nombre' => $datos['nombre'],
                        'nivel' => NivelMantenimiento::from($datos['codigo'])->nivel(),
                        'intervalo_km' => $datos['intervalo_km'],
                        'intervalo_meses' => $datos['intervalo_meses'] ?? null,
                    ],
                );

                $servicio->repuestos()->delete();

                foreach ($datos['repuestos'] as $orden => $repuesto) {
                    $servicio->repuestos()->create([
                        'descripcion' => $repuesto['descripcion'],
                        'codigo' => $repuesto['codigo'] ?? null,
                        'cantidad' => $repuesto['cantidad'] ?? null,
                        'unidad' => $repuesto['unidad'] ?? null,
                        'articulo_id' => $repuesto['articulo_id'] ?? null,
                        'es_servicio' => (bool) ($repuesto['es_servicio'] ?? false),
                        'orden' => $orden,
                    ]);
                }
            }

            // Cada ítem que sale del almacén queda con su artículo (se crea
            // solo, en stock cero) y en su misma unidad; los artículos que
            // el plan ya no usa y nunca se movieron, se van.
            $articulos->asegurar($plan->unsetRelation('servicios'));
            $articulos->limpiar($plan);
        });

        return to_route('mantenimiento.preventivo', ['marca' => $plan->marca])
            ->with('toast', [
                'type' => 'success',
                'message' => "Plan de {$plan->marca} guardado.",
            ]);
    }
}
