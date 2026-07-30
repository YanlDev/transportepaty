<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Papeles que habilitan a una persona a conducir en la flota. Son distintos de
 * TipoDocumento, que corresponde a la unidad y no al conductor.
 */
enum TipoDocumentoConductor: string
{
    use HasLabel;

    /** Licencia de conducir profesional, categoría A-IIIc. */
    case LicenciaConducir = 'licencia_conducir';

    /** Habilitación especial para el transporte de materiales peligrosos. */
    case LicenciaEspecial = 'licencia_especial';

    /** Cajón para papeles sueltos: no entra en el semáforo. */
    case Otro = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::LicenciaConducir => 'Licencia de conducir A-IIIc',
            self::LicenciaEspecial => 'Licencia especial',
            self::Otro => 'Otro',
        };
    }

    /**
     * Etiqueta corta para las columnas estrechas de los listados.
     */
    public function abreviatura(): string
    {
        return match ($this) {
            self::LicenciaConducir => 'Licencia',
            self::LicenciaEspecial => 'Especial',
            self::Otro => 'Otro',
        };
    }

    /**
     * Indica si el documento es exigible para que el conductor pueda salir a
     * ruta. Todos lo son salvo «Otro», que no es un requisito.
     */
    public function esObligatorio(): bool
    {
        return $this !== self::Otro;
    }

    /**
     * Los documentos sin los cuales el conductor no debería manejar.
     *
     * @return array<int, self>
     */
    public static function obligatorios(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $tipo): bool => $tipo->esObligatorio(),
        ));
    }
}
