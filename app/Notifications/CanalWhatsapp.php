<?php

namespace App\Notifications;

use App\Models\User;
use App\Services\AvisosPorWhatsapp;
use App\Services\ContactoWhatsapp;

/**
 * Manda una alerta al celular del usuario por el número de la empresa. No
 * la manda en el momento: la anota y la pone en la cola de WhatsApp, igual
 * que los avisos de un ticket, con sus reintentos y sus ✓✓.
 */
class CanalWhatsapp
{
    public function __construct(
        private readonly AvisosPorWhatsapp $avisos,
        private readonly ContactoWhatsapp $contacto,
    ) {}

    public function send(User $notifiable, Alerta $alerta): void
    {
        $numero = $this->contacto->numeroWhatsapp($notifiable->celular);

        if ($numero === null) {
            return;
        }

        $this->avisos->encolarAlerta($alerta->tipo, $numero, $notifiable->name, $alerta->textoWhatsapp(), usuario: $notifiable);
    }
}
