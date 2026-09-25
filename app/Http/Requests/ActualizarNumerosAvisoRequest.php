<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ActualizarNumerosAvisoRequest extends FormRequest
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
            'telefono' => ['nullable', 'string', 'max:30'],
            'telefono_alterno' => ['nullable', 'string', 'max:30'],
            'whatsapp_adicional' => ['nullable', 'string', 'max:30'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'telefono' => 'celular del conductor',
            'telefono_alterno' => 'celular alterno',
            'whatsapp_adicional' => 'WhatsApp adicional',
        ];
    }
}
