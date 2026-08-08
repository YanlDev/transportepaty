<?php

namespace App\Http\Requests;

use App\Enums\TipoCarga;
use App\Enums\TipoVehiculo;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreViajeManualRequest extends FormRequest
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
            'numero_gr' => ['required', 'string', 'max:255', Rule::unique('viajes', 'numero_gr')],
            'fecha_traslado' => ['required', 'date'],
            'origen' => ['required', 'string'],
            'direccion_destino' => ['required', 'string'],
            'departamento_destino' => ['required', 'string', 'max:255'],
            'cliente' => ['required', 'string', 'max:255'],
            'destinatario' => ['required', 'string', 'max:255'],
            'peso' => ['required', 'numeric', 'min:0.001'],
            'unidad_peso' => ['required', 'string', 'max:20'],
            'tracto_id' => ['required', Rule::exists('vehiculos', 'id')->where('tipo', TipoVehiculo::Tracto->value)],
            'carreta_id' => ['nullable', Rule::exists('vehiculos', 'id')->where('tipo', TipoVehiculo::Carreta->value)],
            'conductor_id' => ['required', 'exists:conductores,id'],
            'tipo_carga' => [
                'required',
                Rule::enum(TipoCarga::class)->except(TipoCarga::excluidosDeViaje()),
            ],
            'observaciones' => ['nullable', 'string'],
            'archivo' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }
}
