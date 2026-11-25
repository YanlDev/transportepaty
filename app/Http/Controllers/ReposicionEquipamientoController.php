<?php

namespace App\Http\Controllers;

use App\Models\ConteoEquipamiento;
use App\Models\ItemEquipamiento;
use App\Models\ReposicionEquipamiento;
use App\Models\Vehiculo;
use App\Services\ArticulosDeEquipamiento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Taller pide al almacén que reponga algo del equipamiento de una unidad, y
 * puede retirar el pedido mientras no se haya atendido.
 */
class ReposicionEquipamientoController extends Controller
{
    /**
     * Un solo pedido abierto por ítem y unidad: pedir otra vez lo corrige.
     */
    public function store(Request $request, Vehiculo $vehiculo, ArticulosDeEquipamiento $articulos): RedirectResponse
    {
        $this->authorize('create', ConteoEquipamiento::class);

        $datos = $request->validate([
            'item_equipamiento_id' => ['required', 'integer', Rule::exists('items_equipamiento', 'id')->where('activo', true)],
            'cantidad' => ['required', 'integer', 'min:1', 'max:99'],
            'nota' => ['nullable', 'string', 'max:300'],
        ], [
            'cantidad.min' => 'Pide al menos una.',
        ]);

        $item = ItemEquipamiento::query()->whereKey($datos['item_equipamiento_id'])->sole();

        if (! $item->aplica_a->incluye($vehiculo->tipo)) {
            throw ValidationException::withMessages([
                'item_equipamiento_id' => "{$item->nombre} no es equipamiento de {$vehiculo->placa}.",
            ]);
        }

        // Para entregarlo o comprarlo, el ítem necesita su artículo.
        $articulos->asegurar();

        ReposicionEquipamiento::query()
            ->vigentes()
            ->where('vehiculo_id', $vehiculo->id)
            ->where('item_equipamiento_id', $item->id)
            ->update(['anulado_at' => now(), 'anulado_por' => $request->user()?->id, 'motivo_anulacion' => 'Se volvió a pedir']);

        ReposicionEquipamiento::create([
            'vehiculo_id' => $vehiculo->id,
            'item_equipamiento_id' => $item->id,
            'cantidad' => $datos['cantidad'],
            'nota' => $datos['nota'] ?? null,
            'user_id' => $request->user()?->id,
        ]);

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Reposición pedida al almacén: {$datos['cantidad']} de {$item->nombre} para {$vehiculo->placa}.",
        ]);
    }

    public function destroy(Request $request, ReposicionEquipamiento $reposicion): RedirectResponse
    {
        $this->authorize('create', ConteoEquipamiento::class);

        abort_if($reposicion->estaAnulado(), 404);

        $reposicion->update(['anulado_at' => now(), 'anulado_por' => $request->user()?->id, 'motivo_anulacion' => 'Retirado por taller']);

        return back()->with('toast', ['type' => 'success', 'message' => 'Pedido de reposición retirado.']);
    }
}
