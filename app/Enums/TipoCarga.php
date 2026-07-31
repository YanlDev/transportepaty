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
     * Si la unidad va cargada o vacía. Se deduce del tipo de carga en vez de
     * pedirse aparte: es el mismo dato dicho de dos maneras, y tenerlo duplicado
     * en el Excel es justamente lo que produce filas que se contradicen solas.
     */
    public function estadoCarga(): EstadoCarga
    {
        return $this === self::Vacio
            ? EstadoCarga::Vacio
            : EstadoCarga::Cargado;
    }

    /**
     * Para quién es el viaje. Todo lo que se mueve dentro del circuito de mina
     * es de Minsur; la carga de terceros que se levanta en Lima es particular.
     *
     * Una unidad vacía todavía no tiene cliente, así que devuelve null en vez
     * de inventarle uno.
     */
    public function cliente(): ?Cliente
    {
        return match ($this) {
            self::Concentrado, self::Metalico, self::Escoria, self::Materiales, self::Sacos => Cliente::Minsur,
            self::Particular => Cliente::Particular,
            self::Vacio => null,
        };
    }

    /**
     * Tramo del circuito al que corresponde esta carga. Es lo que permite
     * detectar saltos imposibles entre un reporte y el siguiente: si la carga
     * de hoy pertenece a una fase que no se alcanza desde la de ayer, alguien
     * escribió mal una de las dos.
     */
    public function fase(): FaseCiclo
    {
        return match ($this) {
            self::Vacio => FaseCiclo::SubidaMina,
            self::Concentrado => FaseCiclo::MinaPisco,
            self::Metalico, self::Escoria, self::Materiales, self::Sacos => FaseCiclo::RetornoPisco,
            self::Particular => FaseCiclo::LimaJuliaca,
        };
    }
}
