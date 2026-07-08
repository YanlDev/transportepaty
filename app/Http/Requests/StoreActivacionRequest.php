<?php

namespace App\Http\Requests;

use App\Enums\ResultadoActivacion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreActivacionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * La autorización fina (rol + vehículo) se resuelve en el controlador.
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
            'fecha' => ['nullable', 'date'],
            'kilometraje' => ['nullable', 'integer', 'min:0'],
            'resultado' => ['required', Rule::enum(ResultadoActivacion::class)],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $esAnomalia = $this->input('resultado') === ResultadoActivacion::Anomalia->value;

                // Si el responsable detectó una anomalía, debe describirla.
                if ($esAnomalia && ! $this->filled('observaciones')) {
                    $validator->errors()->add(
                        'observaciones',
                        'Describe la anomalía detectada durante la activación.',
                    );
                }
            },
        ];
    }
}
