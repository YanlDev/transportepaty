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

    /** Documento de identidad. Su caducidad se registra, pero no inhabilita a
     * nadie para conducir: lo exigible es que el papel esté en el expediente
     * (ver `vencimientoInhabilita`). */
    case Dni = 'dni';

    /** Licencia de conducir profesional, categoría A-IIIc. */
    case LicenciaConducir = 'licencia_conducir';

    /** Habilitación especial para el transporte de materiales peligrosos. */
    case LicenciaEspecial = 'licencia_especial';

    /** Permiso Interno de Conducir de la Unidad Minera San Rafael (Minsur).
     * Tiene que estar en el expediente, pero vencido solo avisa (ver
     * `vencimientoInhabilita`). */
    case Pic = 'pic';

    /** Cajón para papeles sueltos: no entra en el semáforo. */
    case Otro = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::Dni => 'DNI',
            self::LicenciaConducir => 'Licencia de conducir A-IIIc',
            self::LicenciaEspecial => 'Licencia especial',
            self::Pic => 'PIC (permiso interno de conducir)',
            self::Otro => 'Otro',
        };
    }

    /**
     * Etiqueta corta para las columnas estrechas de los listados.
     */
    public function abreviatura(): string
    {
        return match ($this) {
            self::Dni => 'DNI',
            self::LicenciaConducir => 'Licencia',
            self::LicenciaEspecial => 'Especial',
            self::Pic => 'PIC',
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
     * Si estar vencido impide salir a ruta.
     *
     * El DNI es la excepción, y es una distinción real y no una comodidad: un
     * DNI caducado no le quita a nadie la habilitación para conducir —eso lo
     * dan las licencias—, pero sí importa para planilla y trámites, así que la
     * fecha se guarda y se avisa en ámbar en vez de pintar al conductor de
     * rojo y sacarlo de la programación por algo que no lo inhabilita.
     *
     * El PIC va igual por decisión de la empresa: su vencimiento se avisa en
     * ámbar para renovarlo a tiempo, sin sacar al conductor de la programación.
     *
     * Faltar es otra cosa: el papel tiene que estar en el expediente, y un DNI
     * ausente sigue siendo rojo.
     */
    public function vencimientoInhabilita(): bool
    {
        return ! in_array($this, [self::Dni, self::Pic], true);
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
