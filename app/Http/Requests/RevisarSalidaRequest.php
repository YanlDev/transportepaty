<?php

namespace App\Http\Requests;

use App\Enums\ResultadoTaller;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        ];
    }
}
