<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Las dos guías de remisión que acompañan a un viaje. Son documentos distintos
 * y de emisores distintos, así que el viaje lleva las dos por separado: la del
 * remitente la emite el cliente por la mercadería, y la del transportista la
 * emite la empresa por el traslado.
 *
 * Acá solo se guarda el número y el archivo. Emitirlas ante SUNAT es otro
 * asunto y queda fuera.
 */
enum TipoGuia: string
{
    use HasLabel;

    case Remitente = 'remitente';
    case Transportista = 'transportista';

    public function label(): string
    {
        return match ($this) {
            self::Remitente => 'Guía de remisión remitente',
            self::Transportista => 'Guía de remisión transportista',
        };
    }

    public function abreviatura(): string
    {
        return match ($this) {
            self::Remitente => 'GRR',
            self::Transportista => 'GRT',
        };
    }

    /**
     * Nombre de la colección de archivos donde vive el documento de esta guía.
     */
    public function coleccion(): string
    {
        return "guia_{$this->value}";
    }

    /**
     * Columna del viaje donde se guarda su número de serie.
     */
    public function campoNumero(): string
    {
        return "numero_guia_{$this->value}";
    }
}
