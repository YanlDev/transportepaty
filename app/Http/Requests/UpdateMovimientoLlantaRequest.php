<?php

namespace App\Http\Requests;

use App\Enums\TipoMovimientoLlanta;
use App\Models\MovimientoLlanta;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Corregir un movimiento mal anotado: fecha, km, profundidad u observación.
 * Qué llanta y en qué posición no se corrige acá: eso es otro movimiento.
 */
class UpdateMovimientoLlantaRequest extends FormRequest
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
        $movimiento = $this->route('movimiento');
        $tipo = $movimiento instanceof MovimientoLlanta ? $movimiento->tipo : null;

        return [
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'kilometraje' => ['nullable', 'integer', 'min:1', 'max:9999999'],
            'profundidad_mm' => [
                $tipo === TipoMovimientoLlanta::Inspeccion ? 'required' : 'nullable',
                $tipo === TipoMovimientoLlanta::Rotacion ? 'prohibited' : 'numeric',
                'min:0',
                'max:30',
            ],
            'observacion' => [$tipo === TipoMovimientoLlanta::Reparacion ? 'required' : 'nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'profundidad_mm.required' => 'Una medición necesita su profundidad.',
            'observacion.required' => 'Anota qué se reparó.',
            'fecha.before_or_equal' => 'La fecha no puede ser futura.',
        ];
    }
}
