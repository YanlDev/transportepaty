<?php

namespace App\Http\Requests;

use App\Enums\Permiso;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

/**
 * Crear o editar un rol: su nombre y la lista completa de permisos que lleva.
 *
 * Al crearlo se puede partir de otro rol (`copiar_de`) en vez de marcar todo
 * desde cero; en ese caso no se mandan permisos.
 */
class GuardarRolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => mb_strtolower(trim((string) $this->input('name')))]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Role|null $rol */
        $rol = $this->route('rol');

        return [
            'name' => [
                'required',
                'string',
                'max:40',
                Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($rol?->id),
            ],
            'permisos' => ['array'],
            'permisos.*' => ['string', Rule::in(Permiso::valores())],
            'copiar_de' => ['nullable', 'integer', Rule::exists('roles', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => 'nombre del rol'];
    }
}
