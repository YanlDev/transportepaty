<?php

namespace App\Http\Requests;

use App\Enums\TipoDocumento;
use App\Models\Vehiculo;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

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
            'tipo' => ['required', Rule::enum(TipoDocumento::class), $this->reglaTipoAplicable()],
            'nombre' => ['nullable', 'string', 'max:150'],
            'numero' => ['nullable', 'string', 'max:100'],
            'fecha_emision' => ['nullable', 'date'],
            'fecha_vencimiento' => ['nullable', 'date', 'after_or_equal:fecha_emision'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'archivo' => ['required', 'file', 'mimes:jpeg,png,webp,pdf', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tipo.in' => 'Ese tipo de documento no aplica a este vehículo.',
        ];
    }

    /**
     * El formulario ya filtra los tipos según el vehículo (la carreta no lleva
     * SOAT), pero un POST directo podría saltárselo; esta regla lo cierra.
     */
    private function reglaTipoAplicable(): In
    {
        /** @var Vehiculo $vehiculo */
        $vehiculo = $this->route('vehiculo');

        return Rule::in(array_map(
            fn (TipoDocumento $tipo): string => $tipo->value,
            $vehiculo->tipo->documentosAplicables(),
        ));
    }
}
