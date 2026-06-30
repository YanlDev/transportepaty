<?php

namespace App\Http\Requests;

use App\Enums\PosicionFoto;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVehiculoFotoRequest extends FormRequest
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
            'posicion' => ['required', Rule::enum(PosicionFoto::class)],
            'archivo' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
        ];
    }
}
