<?php

namespace App\Http\Requests;

use App\Enums\TipoVehiculo;
use App\Services\Sunat\EmisionGre;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class EmitirGreRequest extends FormRequest
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
            'guias' => ['required', 'array', 'min:1', 'max:20'],
            'guias.*.ruc' => ['required', 'digits:11'],
            'guias.*.serie' => ['required', 'string', 'regex:/^[A-Za-z0-9]{4}$/'],
            'guias.*.numero' => ['required', 'integer', 'min:1'],
            'tracto_id' => ['required', Rule::exists('vehiculos', 'id')->where('tipo', TipoVehiculo::Tracto->value)],
            'carreta_id' => ['nullable', Rule::exists('vehiculos', 'id')->where('tipo', TipoVehiculo::Carreta->value)],
            'conductor_id' => ['required', Rule::exists('conductores', 'id')],
            'fecha_traslado' => ['required', 'date_format:Y-m-d'],
            'pagador' => ['required', Rule::in([EmisionGre::PAGADOR_REMITENTE, EmisionGre::PAGADOR_SUBCONTRATADOR, EmisionGre::PAGADOR_TERCERO])],
            'tuce_tracto' => ['nullable', 'string', 'max:20'],
            'tuce_carreta' => ['nullable', 'string', 'max:20'],
            'ruc_pagador' => ['nullable', 'required_unless:pagador,'.EmisionGre::PAGADOR_REMITENTE, 'digits:11'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ruc_pagador.required_unless' => 'Falta el RUC de quien paga el flete.',
        ];
    }

    /** Se envía con fetch, igual que las consultas: necesita el 422 en JSON. */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'mensaje' => $validator->errors()->first(),
            'errors' => $validator->errors(),
        ], 422));
    }
}
