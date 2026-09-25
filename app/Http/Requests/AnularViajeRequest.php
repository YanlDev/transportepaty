<?php

namespace App\Http\Requests;

use App\Models\Viaje;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AnularViajeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * El motivo es opcional pero útil: «placa mal escrita, se reemplazó por
     * EG03-…» es lo que después explica por qué la fila está en gris.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'motivo' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Una GR ya facturada no se anula de una: primero hay que sacarla de su
     * factura, o el cobro quedaría apoyado en un viaje que ya no cuenta.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $viaje = $this->route('viaje');

                if ($viaje instanceof Viaje && $viaje->factura_id !== null) {
                    $validator->errors()->add(
                        'motivo',
                        'Esta GR ya está en una factura: quítala de la factura antes de anularla.',
                    );
                }
            },
        ];
    }
}
