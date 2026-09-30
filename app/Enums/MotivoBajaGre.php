<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Por qué se da de baja una GR-transportista en SUNAT. Son los dos motivos
 * que ofrece SOL en «Baja de GRE» (parámetro 1031, grabación del 29-09-2026);
 * el valor es el `codMotivo` que se envía.
 */
enum MotivoBajaGre: string
{
    use HasLabel;

    case CambioDeDestinatario = '01';
    case AntesDeIniciarElTraslado = '02';

    public function label(): string
    {
        return match ($this) {
            self::CambioDeDestinatario => 'Durante el traslado, por cambio de destinatario',
            self::AntesDeIniciarElTraslado => 'Antes de iniciar el traslado',
        };
    }
}
