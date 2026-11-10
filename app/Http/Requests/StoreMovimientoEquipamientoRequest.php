<?php

namespace App\Http\Requests;

use App\Models\ItemEquipamiento;
use App\Models\Vehiculo;
use App\Services\EquipamientoDeUnidades;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Pasar equipamiento de una unidad a otra, o darlo de baja. En los dos casos
 * sale de lo que la unidad tiene asignado: no se puede mover ni dar de baja
 * más de lo que tiene. La baja dice por qué.
 */
class StoreMovimientoEquipamientoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $esTraslado = $this->routeIs('mantenimiento.equipamiento.traslados.store');

        return [
            'item_equipamiento_id' => ['required', 'integer', Rule::exists('items_equipamiento', 'id')->where('activo', true)],
            'cantidad' => ['required', 'integer', 'min:1', 'max:999'],
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'vehiculo_destino_id' => $esTraslado
                ? ['required', 'integer', Rule::exists('vehiculos', 'id')->whereNull('deleted_at')]
                : ['prohibited'],
            'motivo' => $esTraslado
                ? ['nullable', 'string', 'max:500']
                : ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $vehiculo = $this->route('vehiculo');

                if (! $vehiculo instanceof Vehiculo || $validator->errors()->isNotEmpty()) {
                    return;
                }

                $item = ItemEquipamiento::query()->findOrFail($this->integer('item_equipamiento_id'));
                $asignado = app(EquipamientoDeUnidades::class)->asignado($vehiculo, $item);

                if ($this->integer('cantidad') > $asignado) {
                    $validator->errors()->add('cantidad', "{$vehiculo->placa} tiene {$asignado} de {$item->nombre}: no se puede sacar más.");
                }

                if (! $this->filled('vehiculo_destino_id')) {
                    return;
                }

                $destino = Vehiculo::query()->findOrFail($this->integer('vehiculo_destino_id'));

                if ($destino->id === $vehiculo->id) {
                    $validator->errors()->add('vehiculo_destino_id', 'Elige otra unidad.');
                } elseif (! $item->aplica_a->incluye($destino->tipo)) {
                    $validator->errors()->add('vehiculo_destino_id', "{$item->nombre} no es equipamiento de {$destino->placa}.");
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'motivo.required' => 'Di por qué se da de baja.',
            'motivo.min' => 'Explica un poco más por qué se da de baja.',
            'vehiculo_destino_id.required' => 'Elige a qué unidad pasa.',
            'fecha.before_or_equal' => 'La fecha no puede ser futura.',
        ];
    }
}
