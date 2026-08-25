<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ActualizarDiasDebidosRequest extends FormRequest
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
            'mes' => ['required', 'date'],
            // Puede ser negativo: significa que el conductor descansó de
            // más ese mes y le debe un día de trabajo a la empresa.
            'dias_debidos' => ['required', 'integer', 'min:-31', 'max:31'],
        ];
    }
}
