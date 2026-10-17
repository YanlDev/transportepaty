<?php

namespace App\Http\Requests;

use App\Models\Llanta;
use App\Models\Vehiculo;
use App\Services\PosicionesLlanta;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * La vuelta del llantero con el medidor: la profundidad del dibujo de las
 * llantas puestas, todas de una vez. Las que se dejan en blanco no se miden.
 */
class StoreMedicionLlantasRequest extends FormRequest
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
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'kilometraje' => ['nullable', 'integer', 'min:1', 'max:9999999'],
            'mediciones' => ['required', 'array', 'min:1'],
            'mediciones.*.posicion' => ['required', 'string', 'distinct', Rule::in(PosicionesLlanta::codigos($this->vehiculo()->tipo))],
            'mediciones.*.profundidad_mm' => ['required', 'numeric', 'min:0', 'max:30'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $montadas = Llanta::query()
                    ->montadas()
                    ->where('vehiculo_id', $this->vehiculo()->id)
                    ->pluck('posicion')
                    ->all();

                foreach (Arr::pluck($validator->validated()['mediciones'], 'posicion') as $indice => $posicion) {
                    if (! in_array($posicion, $montadas, true)) {
                        $validator->errors()->add("mediciones.{$indice}.profundidad_mm", "La posición {$posicion} no tiene llanta.");
                    }
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
            'mediciones.required' => 'Anota la profundidad de al menos una llanta.',
            'mediciones.*.profundidad_mm.max' => 'Más de 30 mm no es una medida de dibujo.',
        ];
    }

    public function vehiculo(): Vehiculo
    {
        $vehiculo = $this->route('vehiculo');

        abort_unless($vehiculo instanceof Vehiculo, 404);

        return $vehiculo;
    }
}
