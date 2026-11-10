<?php

namespace App\Http\Controllers;

use App\Enums\SentidoTramo;
use App\Http\Requests\GuardarAdicionalRequest;
use App\Models\Ruta;
use App\Models\RutaVuelta;
use App\Models\Vuelta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Lo que se le suma a un tramo del ticket después de programado, con su
 * motivo: una ruta de la tabla (la carga posterior: descarga en Moquegua y
 * carga harina en Ilo) o galones sueltos para un desvío que no está en la
 * tabla. Suman al tope como una ruta más, también cuando el tramo ya tiene
 * combustible autorizado; se quitan mientras no lo tenga.
 *
 * Los agrega quien programa: es el permiso de editar el ticket.
 */
class AdicionalTramoController extends Controller
{
    public function store(GuardarAdicionalRequest $request, Vuelta $vuelta): RedirectResponse
    {
        $sentido = $request->enum('sentido', SentidoTramo::class);
        $motivo = $request->string('motivo')->squish()->toString();
        $ruta = $request->ruta();

        DB::transaction(function () use ($request, $vuelta, $sentido, $motivo, $ruta): void {
            $vuelta->rutas()->create([
                ...($ruta === null
                    ? RutaVuelta::adicional($request->float('galones'), $motivo)
                    : [...RutaVuelta::copiaDe($ruta, $ruta->variantes->firstWhere('id', $request->integer('variante_ruta_id'))), 'motivo' => $motivo]),
                'sentido' => $sentido,
                'orden' => (int) $vuelta->rutas()->max('orden') + 1,
                'user_id' => $request->user()?->id,
            ]);

            if ($ruta !== null) {
                $this->sumarParadas($vuelta, $ruta, $sentido);
            }
        });

        return back()->with('toast', [
            'type' => 'success',
            'message' => $ruta === null
                ? 'Adicional agregado: el tope del tramo sube.'
                : "«{$ruta->nombre}» sumada a la {$sentido->label()}: el tope del tramo sube.",
        ]);
    }

    /**
     * El recorrido de la ruta que se suma entra al ticket como paradas
     * nuevas, al final del tramo: así la GR de esa carga encuentra dónde
     * asignarse. Una ruta sin recorrido propio (Camaná) no agrega ninguna.
     */
    private function sumarParadas(Vuelta $vuelta, Ruta $ruta, SentidoTramo $sentido): void
    {
        $orden = (int) $vuelta->paradas()->max('orden');

        foreach ($ruta->paradas as $plantilla) {
            if (blank($plantilla['zona_destino'] ?? null)) {
                continue;
            }

            $vuelta->paradas()->create([
                'sentido' => $sentido,
                'orden' => ++$orden,
                'zona_origen' => $plantilla['zona_origen'],
                'zona_destino' => $plantilla['zona_destino'],
                'lugar_origen' => $plantilla['lugar_origen'] ?? null,
                'lugar_destino' => $plantilla['lugar_destino'] ?? null,
                'tipo_carga' => $plantilla['tipo_carga'],
            ]);
        }
    }

    public function destroy(Vuelta $vuelta, RutaVuelta $adicional): RedirectResponse
    {
        $this->authorize('update', $vuelta);

        abort_unless($adicional->vuelta_id === $vuelta->id && $adicional->esAdicional(), 404);

        if ($vuelta->tramoConGastos($adicional->sentido)) {
            throw ValidationException::withMessages([
                'adicional' => 'El tramo ya tiene combustible autorizado con este adicional: no se quita. Anula ese combustible antes.',
            ]);
        }

        $adicional->delete();

        return back()->with('toast', ['type' => 'success', 'message' => 'Adicional quitado.']);
    }
}
