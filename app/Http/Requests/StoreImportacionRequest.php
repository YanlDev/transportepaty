<?php

namespace App\Http\Requests;

use App\Models\Importacion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreImportacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Importacion::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'archivo' => [
                'required',
                'file',
                'mimes:xlsx',
                'max:10240',
            ],
            // Opcional: si no se manda, se intenta sacar del nombre del
            // archivo (trae la fecha, ej. «...29-07-2026...») y si tampoco se
            // puede, cae al día de hoy.
            'fecha' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'archivo.mimes' => 'El archivo debe ser un Excel (.xlsx).',
        ];
    }
}
