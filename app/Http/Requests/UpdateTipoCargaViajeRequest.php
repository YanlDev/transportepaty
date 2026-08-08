<?php

namespace App\Http\Requests;

use App\Enums\TipoCarga;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTipoCargaViajeRequest extends FormRequest
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
            'tipo_carga' => [
                'required',
                Rule::enum(TipoCarga::class)->except(TipoCarga::excluidosDeViaje()),
            ],
        ];
    }
}
