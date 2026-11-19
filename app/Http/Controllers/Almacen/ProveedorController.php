<?php

namespace App\Http\Controllers\Almacen;

use App\Enums\Moneda;
use App\Http\Controllers\Almacen\Concerns\CatalogosAlmacen;
use App\Http\Controllers\Controller;
use App\Http\Requests\Almacen\ProveedorRequest;
use App\Models\Proveedor;
use App\Services\Sunat\ClienteGreSunat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

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
                'cuenta_pago' => $proveedor->cuenta_pago,
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

        // Quien lo creó desde una compra lo encuentra ya elegido.
        Inertia::flash('proveedor_creado', ['id' => $proveedor->id]);

        return back()->with('toast', ['type' => 'success', 'message' => "Proveedor {$proveedor->nombre()} registrado."]);
    }

    public function update(ProveedorRequest $request, Proveedor $proveedor): RedirectResponse
    {
        $this->authorize('update', $proveedor);

        $proveedor->update($request->validated());

        return back()->with('toast', ['type' => 'success', 'message' => 'Proveedor actualizado.']);
    }

    /**
     * Los datos de un RUC según SUNAT, para llenar el formulario sin
     * escribirlos. Sale por la sesión SOL de la empresa (la misma que emite
     * las guías): la consulta pública de RUC no deja pasar a un programa.
     *
     * Responde siempre 200 y dice si lo encontró: que SUNAT no conteste no es
     * un error de la pantalla, el proveedor se llena a mano como siempre.
     */
    public function consultarRuc(string $ruc, ClienteGreSunat $sunat): JsonResponse
    {
        $this->authorize('create', Proveedor::class);

        if (preg_match('/^\d{11}$/', $ruc) !== 1) {
            return response()->json(['encontrado' => false, 'mensaje' => 'El RUC tiene 11 dígitos.']);
        }

        try {
            $datos = $sunat->contribuyente($ruc);
        } catch (RuntimeException $error) {
            report($error);

            return response()->json(['encontrado' => false, 'mensaje' => 'SUNAT no respondió. Llena los datos a mano o intenta en un momento.']);
        }

        if ($datos === null) {
            return response()->json(['encontrado' => false, 'mensaje' => 'SUNAT no tiene ese RUC.']);
        }

        $limpio = fn (mixed $valor): ?string => in_array(trim((string) $valor), ['', '-'], true) ? null : trim((string) $valor);
        $lugar = array_filter(array_map($limpio, [
            $datos['ubigeo']['desDistrito'] ?? null,
            $datos['ubigeo']['desProvincia'] ?? null,
            $datos['ubigeo']['desDepartamento'] ?? null,
        ]));
        $telefonos = array_filter(array_map($limpio, (array) ($datos['contacto'] ?? [])));

        return response()->json([
            'encontrado' => true,
            'datos' => [
                'razon_social' => $limpio($datos['desRazonSocial'] ?? null),
                'direccion' => $limpio(implode(' - ', array_filter([$limpio($datos['desDireccion'] ?? null), implode(' - ', $lugar)]))),
                'telefono' => $telefonos === [] ? null : implode(' / ', $telefonos),
                'email' => $limpio(mb_strtolower((string) ($datos['codCorreo1'] ?? ''))),
            ],
            // «00» es lo que trae un contribuyente activo y habido; cualquier
            // otro código se avisa para que alguien lo mire antes de comprarle.
            'observado' => ($datos['codEstado'] ?? '00') !== '00' || ($datos['codDomHabido'] ?? '00') !== '00',
        ]);
    }
}
