<?php

namespace App\Http\Requests;

use App\Concerns\ProfileValidationRules;
use App\Concerns\UsernameCanonicalizado;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class StoreUserRequest extends FormRequest
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
        return [
            'name' => $this->nameRules(),
            'username' => $this->usernameRules(),
            // Dato de contacto, nada más: nadie entra al sistema con el correo.
            'email' => $this->emailRules(requerido: false),
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => ['required', Rule::in(Role::pluck('name'))],
        ];
    }
}
