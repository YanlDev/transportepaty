<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateParametroFlotaRequest extends FormRequest
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
            'tamano_flota' => ['required', 'integer', 'min:1'],
            'dias_ano' => ['required', 'numeric', 'min:1', 'max:366'],
            // Los días perdidos se restan de los del año: si entre los tres se
            // comieran el año entero no quedarían días sobre los que repartir
            // los costos fijos y toda la estructura se iría a cero.
            'dias_mantenimiento' => ['required', 'numeric', 'min:0'],
            'dias_certificaciones' => ['required', 'numeric', 'min:0'],
            'dias_sincronizacion' => ['required', 'numeric', 'min:0'],
            'igv_pct' => ['required', 'numeric', 'min:0', 'max:1'],
            'margen_pct_default' => ['required', 'numeric', 'min:0', 'max:0.9'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $perdidos = $this->float('dias_mantenimiento')
                    + $this->float('dias_certificaciones')
                    + $this->float('dias_sincronizacion');

                if ($perdidos >= $this->float('dias_ano')) {
                    $validator->errors()->add(
                        'dias_sincronizacion',
                        'Los días perdidos no pueden llegar a cubrir todo el año: no quedarían días disponibles.',
                    );
                }
            },
        ];
    }
}
