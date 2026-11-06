<?php

namespace App\Http\Requests;

use App\Enums\ResultadoTaller;
use App\Models\Vuelta;
use App\Services\OdometroDeUnidad;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * El apto o no apto de taller. Si no es apta hay que decir por qué y cuándo
 * estará lista, para que programación sepa a qué atenerse.
 */
class RevisarSalidaRequest extends FormRequest
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
        $noApta = Rule::requiredIf($this->input('resultado') === ResultadoTaller::NoApto->value);

        return [
            'resultado' => ['required', Rule::enum(ResultadoTaller::class)],
            'disponible_estimado' => [$noApta, 'nullable', 'date'],
            'motivo' => [$noApta, 'nullable', 'string', 'max:500'],
            'trabajos' => ['nullable', 'string', 'max:2000'],
            // El km con que sale: con el de la llegada, el ticket sabe cuánto
            // recorrió y cuánto rindió el combustible.
            'kilometraje' => ['nullable', 'integer', 'min:1', 'max:9999999'],
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

                /** @var Vuelta $vuelta */
                $vuelta = $this->route('vuelta');
                $error = app(OdometroDeUnidad::class)->error($vuelta->tracto, $this->integer('kilometraje'), OdometroDeUnidad::deSalida($vuelta->id));

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
            'disponible_estimado' => 'fecha y hora estimada',
            'trabajos' => 'trabajos',
            'kilometraje' => 'kilometraje de salida',
        ];
    }
}
