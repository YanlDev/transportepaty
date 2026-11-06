<?php

namespace App\Http\Requests;

use App\Models\Abastecimiento;
use App\Services\OdometroDeUnidad;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Lo que de verdad cargó la unidad, según el voucher del grifo: los galones,
 * el precio y el kilometraje. Puede ser distinto de lo autorizado; la
 * diferencia se ve en la liquidación del ticket.
 */
class RegistrarDespachoRequest extends FormRequest
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
        return [
            'galones_despachados' => ['required', 'numeric', 'min:0.1', 'max:999'],
            'precio_galon' => ['required', 'numeric', 'min:0.01', 'max:99'],
            'kilometraje' => ['nullable', 'integer', 'min:1', 'max:9999999'],
            'despachado_el' => ['required', 'date'],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty() || ! $this->filled('kilometraje')) {
                    return;
                }

                /** @var Abastecimiento $abastecimiento */
                $abastecimiento = $this->route('abastecimiento');

                // Corregir el mismo despacho no compite contra sí mismo.
                if ($abastecimiento->kilometraje === $this->integer('kilometraje')) {
                    return;
                }

                $error = app(OdometroDeUnidad::class)->error($abastecimiento->vehiculo, $this->integer('kilometraje'), OdometroDeUnidad::deDespacho($abastecimiento->id));

                if ($error !== null) {
                    $validator->errors()->add('kilometraje', $error);
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'galones_despachados' => 'galones despachados',
            'precio_galon' => 'precio por galón',
            'despachado_el' => 'fecha del voucher',
        ];
    }
}
