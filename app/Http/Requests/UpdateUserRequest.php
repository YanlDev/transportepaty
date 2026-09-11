<?php

namespace App\Http\Requests;

use App\Concerns\ProfileValidationRules;
use App\Concerns\UsernameCanonicalizado;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UpdateUserRequest extends FormRequest
{
    use ProfileValidationRules, UsernameCanonicalizado;

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
        $usuario = $this->route('user');
        $usuarioId = $usuario instanceof User ? $usuario->id : null;

        return [
            'name' => $this->nameRules(),
            'username' => $this->usernameRules($usuarioId),
            // Dato de contacto, nada más: nadie entra al sistema con el correo.
            'email' => $this->emailRules($usuarioId, requerido: false),
            'role' => ['required', Rule::in(Role::pluck('name'))],
        ];
    }
}
