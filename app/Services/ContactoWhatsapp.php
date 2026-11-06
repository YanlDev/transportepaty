<?php

namespace App\Services;

use App\Models\Ajuste;

/**
 * Los números de los avisos por WhatsApp: el del conductor o el del área en
 * el formato que pide WhatsApp, y el teléfono de la oficina que se cargó en
 * el panel.
 */
class ContactoWhatsapp
{
    /**
     * El teléfono en el formato que pide un enlace de WhatsApp: solo dígitos y
     * con el código de país. Los celulares se guardan con los nueve dígitos de
     * siempre, sin el 51 adelante.
     *
     * Devuelve null si no hay con qué armar un número: sin eso, el botón de
     * avisar abriría un chat vacío.
     */
    public function numeroWhatsapp(?string $telefono): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) $telefono) ?? '';

        if (strlen($digitos) === 9 && str_starts_with($digitos, '9')) {
            return "51{$digitos}";
        }

        // Ya viene con código de país (51 + nueve dígitos) o es un fijo con
        // su prefijo: se manda tal cual mientras tenga largo de teléfono.
        return strlen($digitos) >= 9 ? $digitos : null;
    }

    /** El teléfono de la oficina, tal como se cargó en el panel de WhatsApp. */
    public function telefonoOficina(): ?string
    {
        return Ajuste::valor(Ajuste::TELEFONO_OFICINA);
    }
}
