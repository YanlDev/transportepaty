<?php

namespace App\Http\Controllers\Almacen;

use App\Http\Controllers\Controller;
use App\Http\Requests\Almacen\AnularDocumentoRequest;
use App\Http\Requests\Almacen\StorePagoCompraRequest;
use App\Models\PagoCompra;
use App\Models\ValeCompra;
use App\Services\AlertasDeTaller;
use App\Services\FileUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * Los pagos a proveedores de las compras aprobadas. Transpaty no mueve
 * dinero: quien hizo la transferencia (o pagó en efectivo) lo anota acá con
 * su voucher, y el pedido pasa de «por pagar» a «pagado».
 */
class PagoCompraController extends Controller
{
    public function store(StorePagoCompraRequest $request, ValeCompra $vale, FileUploadService $subidas, AlertasDeTaller $alertas): RedirectResponse
    {
        $this->authorize('pagar', $vale);

        $pago = DB::transaction(function () use ($request, $vale, $subidas): PagoCompra {
            $voucher = $request->hasFile('voucher')
                ? $subidas->guardar($vale, $request->file('voucher'), 'vouchers_pago', 'voucher')
                : null;

            return $vale->pagos()->create([
                ...$request->safe()->except('voucher'),
                'voucher_id' => $voucher?->id,
                'user_id' => $request->user()?->id,
            ]);
        });

        $alertas->pagoRegistrado($vale, $pago, $request->user());

        $falta = $vale->porPagar();

        return back()->with('toast', [
            'type' => 'success',
            'message' => $falta > ValeCompra::CENTIMO
                ? sprintf('Pago anotado. Al %s le faltan %s %s.', $vale->numero(), $vale->moneda->simbolo(), number_format($falta, 2))
                : "Pago anotado: el {$vale->numero()} queda pagado.",
        ]);
    }

    /**
     * Un pago mal anotado no se borra: se anula con su motivo y el monto
     * vuelve a figurar por pagar.
     */
    public function anular(AnularDocumentoRequest $request, ValeCompra $vale, PagoCompra $pago): RedirectResponse
    {
        $this->authorize('pagar', $vale);

        abort_unless($pago->vale_compra_id === $vale->id && ! $pago->estaAnulado(), 404);

        $pago->update([
            'anulado_at' => now(),
            'anulado_por' => $request->user()?->id,
            'motivo_anulacion' => $request->validated('motivo'),
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Pago anulado: el monto vuelve a figurar por pagar.']);
    }
}
