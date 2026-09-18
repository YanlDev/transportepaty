<?php

namespace App\Http\Requests;

use App\Enums\TipoComponente;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Una línea nueva del tarifario entra con su tasa escrita y sin calculadora de
 * apoyo: es un número que ya se usa (un peaje nuevo, un seguro adicional).
 */
class StoreComponenteCostoRequest extends FormRequest
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
            'nombre' => ['required', 'string', 'max:255'],
            'tipo' => ['required', Rule::enum(TipoComponente::class)],
            'tasa' => ['required', 'numeric', 'min:0'],
        ];
    }
}
