<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum EstadoVehiculo: string
{
    use HasLabel;

    case Activo = 'activo';
    case EnMantenimiento = 'en_mantenimiento';
    case Inactivo = 'inactivo';
    case DadoDeBaja = 'dado_de_baja';

    public function label(): string
    {
        return match ($this) {
            self::Activo => 'Operativo',
            self::EnMantenimiento => 'En mantenimiento',
            self::Inactivo => 'Inactivo',
            self::DadoDeBaja => 'Dado de baja',
        };
    }

    /**
     * Indica si el vehículo todavía puede recibir un conductor. Una unidad en
     * mantenimiento sigue siendo asignable porque el conductor no la suelta
     * mientras está en taller; una dada de baja ya salió de la flota.
     */
    public function esAsignable(): bool
    {
        return $this !== self::DadoDeBaja;
    }

    /**
     * Valores de los estados que admiten asignación, para filtrar consultas.
     *
     * @return array<int, string>
     */
    public static function asignables(): array
    {
        return array_values(array_map(
            fn (self $estado): string => $estado->value,
            array_filter(self::cases(), fn (self $estado): bool => $estado->esAsignable()),
        ));
    }
}
