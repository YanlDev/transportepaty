<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Unique;

abstract class ConductorRequest extends FormRequest
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
            'sucursal_id' => ['required', 'exists:sucursales,id'],
            'user_id' => ['nullable', 'exists:users,id'],
            'nombres' => ['required', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:100'],
            'documento' => ['required', 'string', 'max:20', $this->reglaDocumentoUnico()],
            'licencia' => ['nullable', 'string', 'max:30'],
            'categoria_licencia' => ['nullable', 'string', 'max:10'],
            'licencia_vence' => ['nullable', 'date'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'activo' => ['required', 'boolean'],
        ];
    }

    abstract protected function reglaDocumentoUnico(): Unique;
}
