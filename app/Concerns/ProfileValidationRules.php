<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ProfileValidationRules
{
    /**
     * Get the validation rules used to validate user profiles.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function profileRules(?int $userId = null, bool $correoRequerido = true): array
    {
        return [
            'name' => $this->nameRules(),
            'email' => $this->emailRules($userId, $correoRequerido),
        ];
    }

    /**
     * Get the validation rules used to validate usernames.
     *
     * Es el identificador con el que se entra al sistema, así que se limita a
     * letras, números, guiones y guiones bajos: nada que se pueda confundir con
     * un correo ni que dependa de cómo lo escriba el teclado de cada uno.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function usernameRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            'alpha_dash',
            'min:3',
            'max:30',
            $userId === null
                ? Rule::unique(User::class, 'username')
                : Rule::unique(User::class, 'username')->ignore($userId),
        ];
    }

    /**
     * Get the validation rules used to validate user names.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function nameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate user emails.
     *
     * El correo dejó de ser obligatorio cuando el login pasó a ser el usuario:
     * lo carga quien lo tiene, y en la práctica es solo el admin.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function emailRules(?int $userId = null, bool $requerido = true): array
    {
        return [
            $requerido ? 'required' : 'nullable',
            'string',
            'email',
            'max:255',
            $userId === null
                ? Rule::unique(User::class)
                : Rule::unique(User::class)->ignore($userId),
        ];
    }
}
