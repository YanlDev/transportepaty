<?php

namespace App\Rules;

use App\Services\ContactoWhatsapp;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Un celular al que se le pueda escribir por WhatsApp: los nueve dígitos de
 * siempre o con el 51 adelante. Los espacios y guiones no importan.
 */
class CelularWhatsapp implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (app(ContactoWhatsapp::class)->numeroWhatsapp((string) $value) === null) {
            $fail('Escribe un celular válido, por ejemplo 987 654 321.');
        }
    }
}
