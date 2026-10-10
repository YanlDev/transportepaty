<?php

namespace App\Http\Controllers;

use App\Http\Requests\AsociarFacturaRequest;
use App\Http\Requests\ImportarFacturasRequest;
use App\Http\Requests\PatchFacturaRequest;
use App\Http\Requests\StoreFacturaRequest;
use App\Models\Factura;
use App\Models\Viaje;
use App\Services\ImportadorFactura;
use App\Services\RelojOperativo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Emitir, corregir y anular facturas. Facturar es, en la práctica, elegir qué
 * viajes entran: por eso el alta recibe una lista de ids y no un viaje suelto
 * —dos GR de una misma salida se cobran una vez, y una factura quincenal junta
 * diez viajes del mismo cliente—. Un viaje ya facturado puede recibir otra
 * factura: el flete y la estadía, o un cobro partido en dos o tres.
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

            $factura->viajes()->attach($viajeIds);
        });

        return back()->with('toast', [
            'type' => 'success',
            'message' => count($viajeIds) === 1
                ? 'Factura registrada sobre 1 viaje.'
                : 'Factura registrada sobre '.count($viajeIds).' viajes.',
        ]);
    }

    /**
     * Registra facturas subidas en PDF y las asocia con las GR que citan. El
     * detalle de cada archivo —qué se asoció y qué quedó pendiente— vuelve
     * como `importacion_facturas` para mostrarlo en pantalla: un toast no
     * alcanza para decir por qué una GR no se pudo asociar.
     */
    public function importar(ImportarFacturasRequest $request, ImportadorFactura $importador): RedirectResponse
    {
        $this->authorize('create', Factura::class);

        $resultados = array_map(
            fn ($archivo): array => $importador->importar($archivo),
            $request->file('archivos'),
        );

        Inertia::flash('importacion_facturas', $resultados);

        $reconocidas = count(array_filter($resultados, fn (array $resultado): bool => $resultado['reconocida']));
        $conAlertas = count(array_filter($resultados, fn (array $resultado): bool => $resultado['alertas'] !== []));

        return back()->with('toast', [
            'type' => $conAlertas > 0 ? 'warning' : 'success',
            'message' => ($reconocidas === 1 ? '1 factura registrada.' : "{$reconocidas} facturas registradas.")
                .($conAlertas > 0 ? ' Revisa las alertas.' : ''),
        ]);
    }

    /**
     * Asocia a mano una factura con sus GR, desde la bandeja «Facturas por
     * asociar». Se suman a las que ya tenía, sin sacarle ninguna.
     */
    public function asociar(AsociarFacturaRequest $request, Factura $factura): RedirectResponse
    {
        $this->authorize('update', $factura);

        $viajes = $request->viajes();

        $factura->viajes()->syncWithoutDetaching($viajes->pluck('id')->all());

        return back()->with('toast', [
            'type' => 'success',
            'message' => $viajes->count() === 1
                ? "Factura {$factura->numero} asociada a 1 GR."
                : "Factura {$factura->numero} asociada a {$viajes->count()} GR.",
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

        // El precio por GR se guarda como el valor de toda la factura: es la
        // cifra de la que salen el IGV, el total y lo que suma en los totales.
        if (array_key_exists('monto_por_viaje', $datos)) {
            $porViaje = $datos['monto_por_viaje'];
            unset($datos['monto_por_viaje']);

            $datos['monto'] = $porViaje === null
                ? null
                : round((float) $porViaje * $factura->viajes()->count(), 2);
        }

        // Borrar el total es borrar el flete: el valor sale del total y sin él
        // no queda cifra de la que partir.
        if (array_key_exists('total', $datos) && $datos['total'] === null) {
            $datos['monto'] = null;
        }

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
            $factura->viajes()->detach();
            $factura->delete();
        });

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Factura {$factura->numero} anulada.",
        ]);
    }

    /**
     * Saca un viaje de una de sus facturas sin tocar la factura, los demás
     * viajes que cubre ni las otras facturas del viaje. Es la corrección de
     * haber metido una GR de más en el grupo.
     */
    public function desvincular(Factura $factura, Viaje $viaje): RedirectResponse
    {
        $this->authorize('update', $factura);

        abort_unless($factura->viajes()->whereKey($viaje->id)->exists(), 404);

        // Si era la última GR, la factura no se anula: pasa a la bandeja
        // «Facturas por asociar», desde donde se le asignan las correctas o se
        // anula a propósito.
        $factura->viajes()->detach($viaje->id);

        return back()->with('toast', [
            'type' => 'success',
            'message' => "La GR {$viaje->numero_gr} salió de la factura {$factura->numero}.",
        ]);
    }
}
