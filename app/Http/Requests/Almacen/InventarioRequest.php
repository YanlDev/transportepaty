<?php

namespace App\Http\Requests\Almacen;

use App\Enums\EstadoInventario;
use App\Models\InventarioAlmacen;
use App\Rules\FechaDelAlmacen;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Abrir un conteo: la fecha a la que se cuenta.
 */
class InventarioRequest extends FormRequest
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
            'fecha' => ['required', 'date', new FechaDelAlmacen],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Un conteo a la vez: dos abiertos se pisan al cerrarse.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $abierto = InventarioAlmacen::query()->where('estado', EstadoInventario::Abierto)->whereNull('anulado_at')->oldest('id')->first();

                if ($abierto !== null) {
                    $validator->errors()->add('fecha', "El conteo {$abierto->numero()} sigue abierto: ciérralo o anúlalo antes de abrir otro.");
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
        ];
    }
}
