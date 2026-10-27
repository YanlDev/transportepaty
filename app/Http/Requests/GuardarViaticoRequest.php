<?php

namespace App\Http\Requests;

use App\Enums\EstadoVuelta;
use App\Enums\TipoLineaAbastecimiento;
use App\Models\Viatico;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * El depósito de un viático o su corrección: cuánto, cuándo, el número de
 * operación y una nota. Al depositar se elige la línea de la pizarra o, si es
 * un imprevisto, se escribe el concepto. El ticket y la línea no cambian al
 * corregir: si estaban mal, se anula y se deposita de nuevo.
 */
class GuardarViaticoRequest extends FormRequest
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
        $corrigiendo = $this->route('viatico') instanceof Viatico;
        $imprevisto = $corrigiendo
            ? $this->route('viatico')->linea_regla_abastecimiento_id === null
            : ! $this->filled('linea_regla_abastecimiento_id');

        return [
            ...($corrigiendo ? [] : [
                'vuelta_id' => ['required', Rule::exists('vueltas', 'id')->whereNot('estado', EstadoVuelta::Anulada->value)],
                'linea_regla_abastecimiento_id' => ['nullable', Rule::exists('lineas_regla_abastecimiento', 'id')->where('tipo', TipoLineaAbastecimiento::Viatico->value)],
            ]),
            'concepto' => $imprevisto ? ['required', 'string', 'max:120'] : ['prohibited'],
            'monto' => ['required', 'numeric', 'min:0.01', 'max:99999.99'],
            'fecha' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now()->addDay()->toDateString()],
            'numero_operacion' => ['nullable', 'string', 'max:40'],
            'observacion' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'concepto.prohibited' => 'El concepto es el de la pizarra: no se cambia.',
            'fecha.before_or_equal' => 'La fecha del depósito no puede ser futura.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'vuelta_id' => 'ticket',
            'linea_regla_abastecimiento_id' => 'viático de la pizarra',
            'numero_operacion' => 'número de operación',
            'observacion' => 'observación',
        ];
    }
}
