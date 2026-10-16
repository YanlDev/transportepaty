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

    /** Certificado de aptitud médico ocupacional (EMO), anual. */
    case ExamenMedico = 'examen_medico';

    /** Evaluación práctica de manejo, con su fecha de revalidación. */
    case ExamenManejo = 'examen_manejo';

    /** Curso de manejo y transporte de materiales peligrosos (MATPEL). */
    case Matpel = 'matpel';

    /** Curso del Reglamento Interno de Tránsito (RITRAN) de San Rafael.
     * Vence al año del curso. */
    case ManejoDefensivo = 'manejo_defensivo';

    /** Inducción básica (Anexo 4) en la refinería de Pisco. */
    case Anexo4Pisco = 'anexo4_pisco';

    /** Inducción básica (Anexo 4) en la U.M. San Rafael. Vence al año. */
    case Anexo4SanRafael = 'anexo4_san_rafael';

    /** Curso «Conducción eficaz» de Scania (DILER). No vence. */
    case Diler = 'diler';

    /** Certificado de competencias que emite la empresa. Vale 6 meses desde
     * la evaluación, según el propio certificado. */
    case CertificadoCompetencias = 'certificado_competencias';

    /** Cajón para papeles sueltos: no entra en el semáforo. */
    case Otro = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::Dni => 'DNI',
            self::LicenciaConducir => 'Licencia de conducir A-IIIc',
            self::LicenciaEspecial => 'Licencia especial',
            self::Pic => 'PIC (permiso interno de conducir)',
            self::ExamenMedico => 'Examen médico ocupacional',
            self::ExamenManejo => 'Examen de manejo',
            self::Matpel => 'MATPEL',
            self::ManejoDefensivo => 'Manejo defensivo (RITRAN)',
            self::Anexo4Pisco => 'Anexo 4 · Pisco',
            self::Anexo4SanRafael => 'Anexo 4 · San Rafael',
            self::Diler => 'DILER (conducción eficaz Scania)',
            self::CertificadoCompetencias => 'Certificado de competencias',
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
            self::ExamenMedico => 'EMO',
            self::ExamenManejo => 'Ex. manejo',
            self::Matpel => 'MATPEL',
            self::ManejoDefensivo => 'RITRAN',
            self::Anexo4Pisco => 'A4 Pisco',
            self::Anexo4SanRafael => 'A4 S. Rafael',
            self::Diler => 'DILER',
            self::CertificadoCompetencias => 'Competencias',
            self::Otro => 'Otro',
        };
    }

    /**
     * Si entra en el semáforo: faltar pinta de rojo. Todos salvo el DILER
     * —que no vence y tiene su lugar en el expediente sin ser un requisito
     * para salir— y «Otro».
     */
    public function esObligatorio(): bool
    {
        return $this->tieneRanura() && $this !== self::Diler;
    }

    /**
     * El bloque en que se muestra en la ficha del conductor: con trece
     * papeles, agruparlos es lo que permite encontrar uno sin leerlos todos.
     */
    public function grupo(): string
    {
        return match ($this) {
            self::Dni, self::LicenciaConducir, self::LicenciaEspecial => 'Identidad y licencias',
            self::Pic, self::Anexo4SanRafael, self::Anexo4Pisco, self::ManejoDefensivo => 'Accesos a mina',
            self::ExamenMedico, self::ExamenManejo, self::CertificadoCompetencias => 'Salud y evaluaciones',
            self::Matpel, self::Diler => 'Capacitaciones',
            self::Otro => 'Otros papeles',
        };
    }

    /**
     * Si tiene su lugar fijo en el expediente, cargado o vacío. Todos salvo
     * «Otro», que es el cajón de papeles sueltos.
     */
    public function tieneRanura(): bool
    {
        return $this !== self::Otro;
    }

    /**
     * Al conductor externo solo se le piden el DNI y las licencias: el PIC,
     * los exámenes y los cursos de la mina son para el personal de la casa.
     */
    public function seExigeAExterno(): bool
    {
        return in_array($this, [self::Dni, self::LicenciaConducir, self::LicenciaEspecial], true);
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
     * El PIC, los exámenes y los cursos van igual por decisión de la
     * empresa: su vencimiento se avisa en ámbar para renovarlo a tiempo, sin
     * sacar al conductor de la programación. Solo las licencias inhabilitan.
     *
     * Faltar es otra cosa: el papel tiene que estar en el expediente, y un DNI
     * ausente sigue siendo rojo.
     */
    public function vencimientoInhabilita(): bool
    {
        return in_array($this, [self::LicenciaConducir, self::LicenciaEspecial], true);
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
