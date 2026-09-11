<?php

namespace App\Http\Requests;

use App\Models\ConductorDocumento;
use App\Models\VehiculoDocumento;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * La corrección rápida del vencimiento de un documento, en el expediente del
 * conductor y en el del vehículo. Una sola clase porque la regla es la misma en
 * los dos: quien autoriza es el controlador, contra el dueño del expediente.
 */
class ActualizarVencimientoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `present` y no `required`: vaciar el campo es un valor válido —hay
     * documentos que no vencen, como el DNI— y `required` lo rechazaría.
     *
     * El piso lo pone la fecha de emisión que ya tiene guardada el documento,
     * no una que venga en la petición: acá se manda un campo solo, así que
     * `after_or_equal:fecha_emision` no tendría con qué comparar.
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        $emision = $this->fechaDeEmision();

        return [
            'fecha_vencimiento' => array_filter([
                'present',
                'nullable',
                'date',
                $emision !== null ? "after_or_equal:{$emision}" : null,
            ]),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'fecha_vencimiento' => 'fecha de vencimiento',
        ];
    }

    private function fechaDeEmision(): ?string
    {
        $documento = $this->route('documento');

        if (! $documento instanceof ConductorDocumento && ! $documento instanceof VehiculoDocumento) {
            return null;
        }

        return $documento->fecha_emision?->format('Y-m-d');
    }
}
