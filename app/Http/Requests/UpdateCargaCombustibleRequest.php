<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Processing phase: the admin reads the photos and fills in the load data.
 * `precio_por_galon` is derived from `costo_total / galones` in the controller.
 */
class UpdateCargaCombustibleRequest extends FormRequest
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
            'fecha_carga' => ['required', 'date'],
            'odometro' => ['required', 'integer', 'min:0'],
            'galones' => ['required', 'numeric', 'gt:0'],
            'costo_total' => ['nullable', 'numeric', 'min:0'],
            'observaciones' => ['nullable', 'string', 'max:500'],
            'comprobante' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:8192'],
            'odometro_foto' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:8192'],
        ];
    }
}
