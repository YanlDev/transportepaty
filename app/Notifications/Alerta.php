<?php

namespace App\Notifications;

use App\Enums\TipoAlerta;
use App\Models\User;
use Illuminate\Notifications\Notification;

/**
 * Una alerta para un usuario: queda en su campanita y, si así está
 * configurada y tiene celular cargado, le llega por WhatsApp.
 */
class Alerta extends Notification
{
    /**
     * @param  bool  $porApp  si queda en la campanita
     * @param  bool  $porWhatsapp  si sale por WhatsApp (a quien tenga celular)
     * @param  string|null  $whatsapp  el mensaje de WhatsApp ya armado, cuando el aviso tiene su propio formato
     * @param  int|null  $adjunto  el id del archivo (media) que acompaña al mensaje de WhatsApp
     */
    public function __construct(
        public readonly TipoAlerta $tipo,
        public readonly string $titulo,
        public readonly string $mensaje,
        public readonly ?string $url = null,
        public readonly bool $porApp = true,
        public readonly bool $porWhatsapp = false,
        public readonly ?string $whatsapp = null,
        public readonly ?int $adjunto = null,
    ) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return array_values(array_filter([
            $this->porApp ? 'database' : null,
            $this->porWhatsapp && $notifiable->celular ? CanalWhatsapp::class : null,
        ]));
    }

    public function databaseType(object $notifiable): string
    {
        return 'alerta';
    }

    /**
     * @return array{tipo: string, titulo: string, mensaje: string, url: string|null}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'tipo' => $this->tipo->value,
            'titulo' => $this->titulo,
            'mensaje' => $this->mensaje,
            'url' => $this->url,
        ];
    }

    /**
     * El mensaje de WhatsApp: el propio del aviso si lo trae; si no, el
     * título en negrita y el enlace al final.
     */
    public function textoWhatsapp(): string
    {
        return $this->whatsapp ?? implode("\n", array_filter([
            "🔔 *{$this->titulo}*",
            $this->mensaje,
            $this->url,
        ]));
    }
}
