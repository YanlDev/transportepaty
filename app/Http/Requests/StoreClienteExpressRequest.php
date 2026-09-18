<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * El alta mínima de un cliente, para darlo de alta sin salir del formulario
 * que lo necesita —programar una carga, típicamente— y volver a lo que se
 * estaba haciendo.
 *
 * Pide lo indispensable y nada más: el resto de la ficha (teléfono, correo,
 * dirección, notas) se completa después en el padrón, que es donde se
 * mantiene. El RUC sigue siendo obligatorio aunque acá estorbe: es la clave
 * con la que las GR importadas se enlazan al cliente, y un alta sin él
 * terminaría en dos fichas del mismo cliente cuando llegue su primera guía.
 */
class StoreClienteExpressRequest extends FormRequest
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
            'ruc' => ['required', 'digits:11', Rule::unique('clientes', 'ruc')],
            'razon_social' => ['required', 'string', 'max:255'],
            'alias' => ['required', 'string', 'max:60'],
            'contacto' => ['nullable', 'string', 'max:120'],
        ];
    }
}
