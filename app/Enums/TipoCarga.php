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
}
