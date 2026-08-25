<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Qué lleva la unidad. En este circuito la carga no es un dato suelto: define
 * en qué tramo va la unidad y para quién trabaja. De ahí que las reglas del
 * negocio vivan acá y no repartidas por los controladores.
 */
enum TipoCarga: string
{
    use HasLabel;

    case Concentrado = 'concentrado';
    case Metalico = 'metalico';
    case Escoria = 'escoria';
    case Materiales = 'materiales';
    case Particular = 'particular';
    case Sacos = 'sacos';
    case Vacio = 'vacio';

    public function label(): string
    {
        return match ($this) {
            self::Concentrado => 'Concentrado',
            self::Metalico => 'Metálico',
            self::Escoria => 'Escoria',
            self::Materiales => 'Materiales',
            self::Particular => 'Particular',
            self::Sacos => 'Sacos',
            self::Vacio => 'Vacío',
        };
    }

    /**
     * Sacos y Vacío describen el estado de una unidad en ruta abierta (ficha
     * de disponibilidad); no tienen sentido como contenido de un viaje ya
     * cerrado con GR entregada.
     *
     * @return list<self>
     */
    public static function excluidosDeViaje(): array
    {
        return [self::Sacos, self::Vacio];
    }

    /**
     * Opciones para el selector de tipo de carga en un viaje ya cerrado.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function opcionesDeViaje(): array
    {
        $excluidos = array_map(fn (self $caso): string => $caso->value, self::excluidosDeViaje());

        return array_values(array_filter(
            self::options(),
            fn (array $opcion): bool => ! in_array($opcion['value'], $excluidos, true),
        ));
    }

    /**
     * RUC de Minsur S.A. — el único cliente donde el prefijo de serie de su
     * guía remitente identifica la carga de forma consistente (verificado
     * contra el histórico completo de guías remitente descargadas de SUNAT,
     * agosto 2026). Otros clientes no numeran así, así que esta regla no
     * aplica fuera de este RUC.
     */
    public const RUC_MINSUR = '20100136741';

    /**
     * T007 = concentrado de estaño (mina → fundición). T004 = metálico /
     * estaño refinado (fundición → puerto Callao, para exportar vía Impala
     * Terminals). T005 = escoria (subproducto de la fundición). T012 y T008
     * = materiales — insumos y repuestos de mina, no el mineral en sí (el
     * fixture de prueba `gr-minsur-ransa-peso-sin-decimales.pdf` es de esta
     * serie).
     *
     * @var array<string, self>
     */
    private const SERIES_MINSUR = [
        'T007' => self::Concentrado,
        'T004' => self::Metalico,
        'T005' => self::Escoria,
        'T012' => self::Materiales,
        'T008' => self::Materiales,
    ];

    /**
     * Deriva el tipo de carga a partir de la serie de una guía remitente de
     * Minsur (ej. «T007 - 9609» → Concentrado). Devuelve null si la serie no
     * es una de las reconocidas — quien llame decide qué hacer con eso (no
     * clasificar, dejar en Particular, etc.), esto no asume un default.
     */
    public static function desdeGuiaRemitenteMinsur(string $numeroGuiaRemitente): ?self
    {
        $serie = strtoupper(trim(explode('-', $numeroGuiaRemitente)[0]));

        return self::SERIES_MINSUR[$serie] ?? null;
    }
}
