<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * El recorrido de un aviso por WhatsApp, en el orden en que avanza. Los tres
 * últimos los confirma WhatsApp (los ✓, ✓✓ y ✓✓ azules del chat).
 */
enum EstadoEnvio: string
{
    use HasLabel;

    case Pendiente = 'pendiente';
    case Enviado = 'enviado';
    case Entregado = 'entregado';
    case Leido = 'leido';
    case Fallido = 'fallido';

    public function label(): string
    {
        return match ($this) {
            self::Pendiente => 'Enviando…',
            self::Enviado => 'Enviado',
            self::Entregado => 'Entregado',
            self::Leido => 'Leído',
            self::Fallido => 'No se pudo enviar',
        };
    }

    /**
     * Qué tan lejos llegó: un recibo atrasado (llega «entregado» después de
     * «leído») no hace retroceder el estado.
     */
    public function avance(): int
    {
        return match ($this) {
            self::Fallido => -1,
            self::Pendiente => 0,
            self::Enviado => 1,
            self::Entregado => 2,
            self::Leido => 3,
        };
    }
}
