<?php

namespace App\Http\Requests;

use App\Enums\TipoAlerta;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

/**
 * Cómo se avisa un tipo de alerta: prendida o no, por qué canales y a quién.
 * Los resúmenes diarios además llevan su hora.
 */
class ActualizarConfiguracionAlertaRequest extends FormRequest
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
        $tipo = $this->route('tipo');
        $esResumen = $tipo instanceof TipoAlerta && $tipo->esResumenDiario();

        return [
            'activa' => ['required', 'boolean'],
            'por_app' => ['required', 'boolean'],
            'por_whatsapp' => ['required', 'boolean'],
            'roles' => ['present', 'array'],
            'roles.*' => ['string', Rule::in(Role::pluck('name'))],
            'usuarios' => ['present', 'array'],
            'usuarios.*' => ['integer', Rule::exists('users', 'id')],
            'areas' => ['present', 'array'],
            'areas.*' => ['integer', Rule::exists('areas_aviso', 'id')],
            'hora' => [Rule::requiredIf($esResumen), 'nullable', 'date_format:H:i'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'hora.required' => 'Elige a qué hora sale el resumen.',
        ];
    }
}
