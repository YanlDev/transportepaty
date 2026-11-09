<?php

namespace App\Http\Controllers\Almacen;

use App\Enums\Moneda;
use App\Http\Controllers\Almacen\Concerns\CatalogosAlmacen;
use App\Http\Controllers\Controller;
use App\Http\Requests\Almacen\ProveedorRequest;
use App\Models\Proveedor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El padrón de proveedores, con cuánto se les compró.
 */
class ProveedorController extends Controller
{
    use CatalogosAlmacen;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Proveedor::class);

        $veCostos = $this->veCostos($request->user());
        $filtros = ['buscar' => $request->string('buscar')->trim()->value()];

        $proveedores = Proveedor::query()
            ->when($filtros['buscar'] !== '', fn ($query) => $query->buscar($filtros['buscar']))
            ->withCount(['ingresos' => fn ($query) => $query->vigentes()])
            ->withSum(['ingresos' => fn ($query) => $query->vigentes()], 'costo_total')
            ->withMax(['ingresos' => fn ($query) => $query->vigentes()], 'fecha')
            ->withSum(['ingresos as comprado_usd' => fn ($query) => $query->vigentes()->where('moneda', Moneda::Dolares)], 'total')
            ->orderByDesc('activo')
            ->orderBy('razon_social')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (Proveedor $proveedor): array => [
                'id' => $proveedor->id,
                'ruc' => $proveedor->ruc,
                'razon_social' => $proveedor->razon_social,
                'nombre_comercial' => $proveedor->nombre_comercial,
                'contacto' => $proveedor->contacto,
                'telefono' => $proveedor->telefono,
                'email' => $proveedor->email,
                'direccion' => $proveedor->direccion,
                'condicion_pago' => $proveedor->condicion_pago,
                'notas' => $proveedor->notas,
                'activo' => $proveedor->activo,
                'compras' => (int) $proveedor->getAttribute('ingresos_count'),
                'comprado' => $this->costo($veCostos, (float) $proveedor->getAttribute('ingresos_sum_costo_total')),
                'ultima_compra' => $proveedor->getAttribute('ingresos_max_fecha'),
                // Lo comprado en dólares, en dólares y con IGV: como dicen sus facturas.
                'comprado_usd' => $this->costo($veCostos, (float) $proveedor->getAttribute('comprado_usd')),
            ]);

        return Inertia::render('almacen/proveedores/index', [
            'proveedores' => $proveedores,
            'filtros' => $filtros,
        ]);
    }

    public function store(ProveedorRequest $request): RedirectResponse
    {
        $this->authorize('create', Proveedor::class);

        $proveedor = Proveedor::create($request->validated());

        return back()->with('toast', ['type' => 'success', 'message' => "Proveedor {$proveedor->nombre()} registrado."]);
    }

    public function update(ProveedorRequest $request, Proveedor $proveedor): RedirectResponse
    {
        $this->authorize('update', $proveedor);

        $proveedor->update($request->validated());

        return back()->with('toast', ['type' => 'success', 'message' => 'Proveedor actualizado.']);
    }
}
