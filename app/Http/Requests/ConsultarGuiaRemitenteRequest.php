<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ConsultarGuiaRemitenteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ruc' => ['required', 'digits:11'],
            'serie' => ['required', 'string', 'regex:/^[A-Za-z0-9]{4}$/'],
            'numero' => ['required', 'integer', 'min:1', 'max:99999999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ruc.digits' => 'El RUC tiene 11 dígitos.',
            'serie.regex' => 'La serie tiene 4 caracteres (ej. T007).',
        ];
    }

    /**
     * La pantalla la consulta con fetch, no con una visita de Inertia: un
     * redirect con los errores en sesión no le sirve, necesita el 422.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'mensaje' => $validator->errors()->first(),
            'errors' => $validator->errors(),
        ], 422));
    }
}
