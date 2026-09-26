<?php

namespace App\Jobs;

use App\Models\EnvioWhatsapp;
use App\Services\AvisosPorWhatsapp;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Manda por WhatsApp un aviso ya anotado. Reintenta si WhatsApp no responde
 * —el número pudo estar reconectando— y, si se rinde, lo deja como fallido
 * para que se vea en Programación.
 */
class EnviarAvisoWhatsapp implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 30];

    public function __construct(public EnvioWhatsapp $envio)
    {
        $this->onQueue('whatsapp');
    }

    public function handle(AvisosPorWhatsapp $avisos): void
    {
        $avisos->mandar($this->envio);
    }

    public function failed(?Throwable $error): void
    {
        app(AvisosPorWhatsapp::class)->marcarFallido($this->envio, $error?->getMessage() ?? 'Error desconocido.');
    }
}
