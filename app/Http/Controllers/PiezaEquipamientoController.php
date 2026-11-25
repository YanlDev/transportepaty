<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePiezaEquipamientoRequest;
use App\Models\ConteoEquipamiento;
use App\Models\ItemEquipamiento;
use App\Models\PiezaEquipamiento;
use App\Models\Vehiculo;
use App\Services\EquipamientoDeUnidades;
use App\Services\PiezasDeEquipamiento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Las piezas de equipamiento con código propio: ponerle código a la que
 * llegó sin él (del almacén o de otra unidad) y anotar sus datos.
 */
class PiezaEquipamientoController extends Controller
{
    /**
     * Les pone código a las piezas de un ítem que la unidad tiene y todavía
     * no están identificadas.
     */
    public function store(Request $request, Vehiculo $vehiculo, EquipamientoDeUnidades $equipamiento, PiezasDeEquipamiento $piezas): RedirectResponse
    {
        $this->authorize('create', ConteoEquipamiento::class);

        $datos = $request->validate([
            'item_equipamiento_id' => ['required', 'integer', Rule::exists('items_equipamiento', 'id')->where('activo', true)->where('por_pieza', true)],
        ], [
            'item_equipamiento_id.exists' => 'Ese ítem no se lleva pieza por pieza.',
        ]);

        $item = ItemEquipamiento::query()->whereKey($datos['item_equipamiento_id'])->sole();
        $nuevas = $piezas->identificar($vehiculo, $item, $equipamiento->asignado($vehiculo, $item), userId: $request->user()?->id);

        return back()->with('toast', $nuevas->isEmpty()
            ? ['type' => 'warning', 'message' => "Todas las piezas de {$item->nombre} de {$vehiculo->placa} ya tienen código."]
            : ['type' => 'success', 'message' => 'Código nuevo: '.$nuevas->pluck('codigo')->join(', ').'. Márcalo en la pieza.']);
    }

    public function update(UpdatePiezaEquipamientoRequest $request, PiezaEquipamiento $pieza): RedirectResponse
    {
        $this->authorize('create', ConteoEquipamiento::class);

        $pieza->update($request->validated());

        return back()->with('toast', ['type' => 'success', 'message' => "{$pieza->codigo} guardado."]);
    }
}
