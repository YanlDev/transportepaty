<?php

namespace App\Http\Requests;

use App\Enums\TipoDocumento;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVehiculoDocumentoRequest extends FormRequest
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
            'tipo' => ['required', Rule::enum(TipoDocumento::class)],
            'nombre' => ['nullable', 'string', 'max:150'],
            'numero' => ['nullable', 'string', 'max:100'],
            'fecha_emision' => ['nullable', 'date'],
            'fecha_vencimiento' => ['nullable', 'date', 'after_or_equal:fecha_emision'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'archivo' => ['required', 'file', 'mimes:jpeg,png,webp,pdf', 'max:10240'],
        ];
    }
}
