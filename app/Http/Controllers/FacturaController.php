<?php

namespace App\Http\Controllers;

use App\Http\Requests\PatchFacturaRequest;
use App\Http\Requests\StoreFacturaRequest;
use App\Models\Factura;
use App\Models\Viaje;
use App\Services\RelojOperativo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Emitir, corregir y anular facturas. Facturar es, en la práctica, elegir qué
 * viajes entran: por eso el alta recibe una lista de ids y no un viaje suelto
 * —dos GR de una misma salida se cobran una vez, y una factura quincenal junta
 * diez viajes del mismo cliente.
 *
 * Todo se edita desde la propia tabla, celda por celda, así que las
 * correcciones llegan de a un campo por `update()` en vez de como un
 * formulario completo.
 */
class FacturaController extends Controller
{
    public function store(StoreFacturaRequest $request): RedirectResponse
    {
        $this->authorize('create', Factura::class);

        $datos = $request->validated();

        /** @var list<int> $viajeIds */
        $viajeIds = $datos['viaje_ids'];
        unset($datos['viaje_ids']);

        // En una transacción: una factura creada cuyos viajes no quedaron
        // enlazados es peor que no haberla creado — se vería como emitida y
        // los viajes seguirían apareciendo por facturar.
        DB::transaction(function () use ($datos, $viajeIds): void {
            $factura = Factura::create([
                ...$datos,
                'numero' => Str::upper(trim($datos['numero'])),
                // Sin fecha de emisión explícita se asume hoy: es lo que pasa
                // el 99% de las veces —se registra la factura el día que se
                // emite— y ahorra tocar una celda más. El «hoy» es el de Lima,
                // no el del servidor: una factura cargada a las 20:00 se emite
                // el día que el contador tiene en el calendario, no el
                // siguiente.
                'fecha_emision' => $datos['fecha_emision'] ?? RelojOperativo::hoy(),
                'moneda' => $datos['moneda'] ?? 'PEN',
            ]);

            Viaje::query()->whereIn('id', $viajeIds)->update(['factura_id' => $factura->id]);
        });

        return back()->with('toast', [
            'type' => 'success',
            'message' => count($viajeIds) === 1
                ? 'Factura registrada sobre 1 viaje.'
                : 'Factura registrada sobre '.count($viajeIds).' viajes.',
        ]);
    }

    /**
     * Guarda una celda. Llega solo el campo tocado, así que se escribe
     * exactamente lo validado y nada más: pisar el resto con valores por
     * defecto borraría lo que otra celda acaba de guardar.
     */
    public function update(PatchFacturaRequest $request, Factura $factura): RedirectResponse
    {
        $this->authorize('update', $factura);

        $datos = $request->validated();

        if (array_key_exists('numero', $datos)) {
            $datos['numero'] = Str::upper(trim($datos['numero']));
        }

        // Sin fecha de pago no hay cuenta que valga: dejarla apuntando a un
        // banco sería decir que se cobró por ahí algo que no se ha cobrado.
        if (array_key_exists('fecha_pago', $datos) && $datos['fecha_pago'] === null) {
            $datos['cuenta_bancaria_id'] = null;
        }

        $factura->update($datos);

        return back();
    }

    /**
     * Anula la factura. Los viajes no se borran con ella: vuelven a estar
     * disponibles para facturarse de nuevo, que es justamente lo que se busca
     * al anular una emitida por error.
     */
    public function destroy(Factura $factura): RedirectResponse
    {
        $this->authorize('delete', $factura);

        DB::transaction(function () use ($factura): void {
            $factura->viajes()->update(['factura_id' => null]);
            $factura->delete();
        });

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Factura anulada. Sus viajes volvieron a quedar sin facturar.',
        ]);
    }

    /**
     * Saca un viaje de su factura sin tocar la factura ni los demás viajes que
     * cubre. Es la corrección de haber metido una GR de más en el grupo.
     */
    public function desvincular(Viaje $viaje): RedirectResponse
    {
        $factura = $viaje->factura;

        abort_if($factura === null, 404);

        $this->authorize('update', $factura);

        $viaje->update(['factura_id' => null]);

        // Una factura que se quedó sin ningún viaje ya no cobra nada: se anula
        // sola en vez de quedar colgada sin forma de llegar a ella desde la
        // tabla, que se recorre por viaje.
        if (! $factura->viajes()->exists()) {
            $factura->delete();
        }

        return back()->with('toast', [
            'type' => 'success',
            'message' => "La GR {$viaje->numero_gr} volvió a quedar sin facturar.",
        ]);
    }
}
