<?php

namespace App\Http\Controllers;

use App\Enums\Permiso;
use App\Models\Factura;
use App\Services\RelojOperativo;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Las detracciones que la empresa tiene que depositar por los clientes que le
 * pagan el total de la factura. No es plata por cobrar —la factura ya está
 * pagada cuando el cliente depositó—: es plata de la empresa que tiene que
 * pasar al Banco de la Nación. Acá se ve cuáles faltan y se registra el
 * depósito con su constancia.
 */
class DetraccionController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Factura::class);

        $hoy = RelojOperativo::fechaDeHoy();
        $puedeRegistrar = $request->user()?->checkPermissionTo(Permiso::CobranzaGestionar) ?? false;

        $pendientes = Factura::query()
            ->where('detraccion_por_empresa', true)
            ->where('detraccion', '>', 0)
            ->whereNull('fecha_detraccion')
            ->with('clienteDelPadron:id,ruc,alias')
            // Primero las que el cliente ya pagó, de la más antigua a la más nueva.
            ->orderByRaw('fecha_pago is null')
            ->orderBy('fecha_pago')
            ->orderBy('fecha_emision')
            ->get()
            ->map(fn (Factura $factura): array => [
                'id' => $factura->id,
                'numero' => $factura->numero,
                'cliente' => $factura->nombreCliente(),
                'fecha_emision' => $factura->fecha_emision->toDateString(),
                'total' => (float) $factura->total,
                'detraccion' => (float) $factura->detraccion,
                // Con el pago del cliente adentro ya toca depositarla.
                'cobrada' => $factura->fecha_pago !== null,
                'fecha_pago' => $factura->fecha_pago?->toDateString(),
                'dias_desde_el_cobro' => $factura->fecha_pago === null ? null : (int) $factura->fecha_pago->diffInDays($hoy),
                'registrable' => $puedeRegistrar,
            ]);

        return Inertia::render('contabilidad/detracciones', [
            'pendientes' => $pendientes->values()->all(),
            'totales' => [
                'por_depositar' => round((float) $pendientes->where('cobrada', true)->sum('detraccion'), 2),
                'facturas_por_depositar' => $pendientes->where('cobrada', true)->count(),
                'sin_cobrar' => round((float) $pendientes->where('cobrada', false)->sum('detraccion'), 2),
                'facturas_sin_cobrar' => $pendientes->where('cobrada', false)->count(),
            ],
        ]);
    }
}
