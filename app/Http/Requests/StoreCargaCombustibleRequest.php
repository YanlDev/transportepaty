<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Two ways to register a load:
 *  - Captura (conductor): solo sube las fotos del comprobante y el odómetro.
 *  - Registro directo (admin): ingresa los datos (galones, odómetro…); las
 *    fotos son opcionales.
 */
class StoreCargaCombustibleRequest extends FormRequest
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
            'fecha_carga' => ['nullable', 'date'],
            'odometro' => ['nullable', 'integer', 'min:0'],
            'galones' => ['nullable', 'numeric', 'gt:0'],
            'costo_total' => ['nullable', 'numeric', 'min:0'],
            'comprobante' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:8192'],
            'odometro_foto' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:8192'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $tieneFotos = $this->hasFile('comprobante') && $this->hasFile('odometro_foto');
            $registroDirecto = $this->filled('galones');

            if (! $tieneFotos && ! $registroDirecto) {
                $validator->errors()->add(
                    'galones',
                    'Sube las fotos del comprobante y el odómetro, o ingresa los datos de la carga.',
                );
            }

            // En registro directo el odómetro es obligatorio.
            if ($registroDirecto && $this->input('odometro') === null) {
                $validator->errors()->add('odometro', 'Ingresa el odómetro de la carga.');
            }
        });
    }
}
