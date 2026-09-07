<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Catálogo 20 de SUNAT: por qué se mueve la mercadería. En la guía del
 * transportista el motivo no lo decide Paty sino el remitente, así que se
 * copia del documento que sustenta el traslado. Solo están los códigos que
 * aparecen en la operación real; el catálogo completo es más largo.
 */
enum MotivoTraslado: string
{
    use HasLabel;

    case Venta = '01';
    case Compra = '02';
    case TrasladoEntreEstablecimientos = '04';
    case TrasladoEmisorItinerante = '18';
    case Importacion = '08';
    case Exportacion = '09';
    case Otros = '13';

    public function label(): string
    {
        return match ($this) {
            self::Venta => 'Venta',
            self::Compra => 'Compra',
            self::TrasladoEntreEstablecimientos => 'Traslado entre establecimientos de la misma empresa',
            self::TrasladoEmisorItinerante => 'Traslado emisor itinerante CP',
            self::Importacion => 'Importación',
            self::Exportacion => 'Exportación',
            self::Otros => 'Otros',
        };
    }
}
