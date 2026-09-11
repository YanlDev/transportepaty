<?php

namespace App\Concerns;

use Illuminate\Support\Str;

/**
 * Normaliza el usuario antes de validarlo.
 *
 * Fortify pasa el login por `CanonicalizeUsername`, que lo baja a minúsculas
 * antes de buscar la cuenta. Sin hacer lo mismo al crearla, un usuario guardado
 * como «JPerez» no coincidiría nunca con el «jperez» que llega del formulario
 * de login: la cuenta existiría y no podría entrar.
 */
trait UsernameCanonicalizado
{
    protected function prepareForValidation(): void
    {
        if ($this->has('username')) {
            $this->merge([
                'username' => Str::lower(trim((string) $this->input('username'))),
            ]);
        }

        // Un correo vacío es no tener correo, no tener la cadena vacía: la
        // columna es única y dos cadenas vacías chocarían entre sí.
        if ($this->has('email') && trim((string) $this->input('email')) === '') {
            $this->merge(['email' => null]);
        }
    }
}
