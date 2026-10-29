<?php

namespace App\Http\Controllers;

use App\Enums\Permiso;
use App\Models\PlanMantenimiento;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La puerta única de la configuración: las rutas y tramos, la pizarra, los
 * planes de mantenimiento, los costos, los usuarios… Cada ajuste sigue en
 * su pantalla; acá se juntan para no tener que acordarse dónde estaba cada
 * engranaje. Cada quien ve solo lo que puede tocar.
 */
class ConfiguracionController extends Controller
{
    public function index(Request $request): Response
    {
        $puedePlanes = $request->user()?->checkPermissionTo(Permiso::MantenimientoConfigurar) ?? false;

        return Inertia::render('configuracion/index', [
            'planes' => $puedePlanes
                ? PlanMantenimiento::query()
                    ->orderBy('marca')
                    ->get(['id', 'marca', 'nombre'])
                    ->map(fn (PlanMantenimiento $plan): array => ['id' => $plan->id, 'marca' => $plan->marca, 'nombre' => $plan->nombre])
                    ->all()
                : [],
        ]);
    }
}
