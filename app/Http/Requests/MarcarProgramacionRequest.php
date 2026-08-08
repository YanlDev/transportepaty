<?php

namespace App\Http\Requests;

use App\Enums\EstadoProgramacion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MarcarProgramacionRequest extends FormRequest
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
            'fecha' => ['required', 'date'],
            'estado' => ['required', Rule::enum(EstadoProgramacion::class)],
            'destino' => ['nullable', 'string'],
            'cliente' => ['nullable', 'string'],
            'observaciones' => ['nullable', 'string'],
        ];
    }
}
