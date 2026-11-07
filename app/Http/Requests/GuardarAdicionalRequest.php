<?php

namespace App\Http\Requests;

use App\Enums\SentidoTramo;
use App\Models\Vuelta;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Galones extra para un tramo de este ticket, con su motivo: un desvío que
 * no se repite (descargar en Lurín antes de subir). Lo agrega quien
 * programa; lo que se repite va como complemento del catálogo.
 */
class GuardarAdicionalRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Vuelta $vuelta */
        $vuelta = $this->route('vuelta');

        return $this->user()?->can('update', $vuelta) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sentido' => ['required', Rule::enum(SentidoTramo::class)],
            'galones' => ['required', 'numeric', 'min:1', 'max:300'],
            'motivo' => ['required', 'string', 'min:3', 'max:180'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['sentido' => 'tramo'];
    }
}
