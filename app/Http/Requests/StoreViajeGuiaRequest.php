<?php

namespace App\Http\Requests;

use App\Enums\TipoGuia;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Carga del archivo de una guía de remisión. El número se guarda con el viaje;
 * acá solo viaja el documento, que puede ser el PDF impreso o el XML de la guía
 * electrónica.
 */
class StoreViajeGuiaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('viaje')) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tipo' => ['required', Rule::enum(TipoGuia::class)],
            'archivo' => ['required', 'file', 'mimes:jpeg,png,webp,pdf,xml', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'archivo.mimes' => 'La guía debe ser un PDF, una imagen o el XML de la guía electrónica.',
        ];
    }
}
