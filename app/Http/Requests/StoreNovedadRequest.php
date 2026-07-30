<?php

namespace App\Http\Requests;

use App\Enums\TipoNovedad;
use App\Enums\TipoVehiculo;
use App\Models\Novedad;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNovedadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Novedad::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tracto_id' => [
                'required',
                Rule::exists('vehiculos', 'id')->where('tipo', TipoVehiculo::Tracto->value),
            ],
            'tipo' => ['required', Rule::enum(TipoNovedad::class)],
            'desde' => ['required', 'date'],
            'motivo' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'tracto_id' => 'unidad',
            'tipo' => 'tipo de novedad',
        ];
    }
}
