<?php

namespace App\Http\Controllers;

use App\Enums\Moneda;
use App\Http\Requests\StoreCuentaBancariaRequest;
use App\Http\Requests\UpdateCuentaBancariaRequest;
use App\Models\CuentaBancaria;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El catálogo de cuentas de la empresa. Es lo que alimenta la columna
 * «entidad» de la cobranza: sin esta lista, el banco por el que entró un pago
 * se escribiría a mano y el mismo banco terminaría con tres grafías distintas.
 *
 * Todo en una sola pantalla —alta, edición y baja en diálogos— porque son unas
 * pocas filas que casi nunca cambian; un create/edit aparte sería navegación
 * de más para algo que se toca dos veces al año.
 */
class CuentaBancariaController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', CuentaBancaria::class);

        $cuentas = CuentaBancaria::query()
            ->withCount('facturas')
            // Las activas arriba: las cerradas se conservan solo para que las
            // facturas viejas sigan diciendo por dónde se cobraron.
            ->orderByDesc('activa')
            ->orderBy('alias')
            ->get()
            ->map(fn (CuentaBancaria $cuenta): array => [
                'id' => $cuenta->id,
                'banco' => $cuenta->banco,
                'alias' => $cuenta->alias,
                'numero_cuenta' => $cuenta->numero_cuenta,
                'cci' => $cuenta->cci,
                'moneda' => $cuenta->moneda->value,
                'moneda_label' => $cuenta->moneda->label(),
                'activa' => $cuenta->activa,
                'notas' => $cuenta->notas,
                'facturas_count' => $cuenta->facturas_count,
            ]);

        return Inertia::render('contabilidad/cuentas', [
            'cuentas' => $cuentas,
            'monedas' => Moneda::options(),
        ]);
    }

    public function store(StoreCuentaBancariaRequest $request): RedirectResponse
    {
        $this->authorize('create', CuentaBancaria::class);

        CuentaBancaria::create($request->validated());

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Cuenta registrada.',
        ]);
    }

    public function update(UpdateCuentaBancariaRequest $request, CuentaBancaria $cuenta): RedirectResponse
    {
        $this->authorize('update', $cuenta);

        $cuenta->update($request->validated());

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Cuenta actualizada.',
        ]);
    }

    /**
     * Solo se borra la cuenta que nunca cobró nada (lo comprueba la policy).
     * Para el resto la interfaz ofrece desactivarla, que la saca de los
     * selectores sin borrar el rastro de las facturas ya cobradas.
     */
    public function destroy(CuentaBancaria $cuenta): RedirectResponse
    {
        $this->authorize('delete', $cuenta);

        $cuenta->delete();

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Cuenta eliminada.',
        ]);
    }
}
