<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;
use App\Models\User;

/**
 * El área de la empresa que pide un vale de gasto. Sale del rol de quien
 * pide, y es por lo que después se agrupan los egresos.
 */
enum AreaGasto: string
{
    use HasLabel;

    case Gerencia = 'gerencia';
    case Operaciones = 'operaciones';
    case Taller = 'taller';
    case Abastecimiento = 'abastecimiento';
    case Contabilidad = 'contabilidad';
    case Rrhh = 'rrhh';
    case Almacen = 'almacen';
    case Administracion = 'administracion';

    public function label(): string
    {
        return match ($this) {
            self::Gerencia => 'Gerencia',
            self::Operaciones => 'Operaciones',
            self::Taller => 'Taller',
            self::Abastecimiento => 'Abastecimiento',
            self::Contabilidad => 'Contabilidad',
            self::Rrhh => 'Recursos humanos',
            self::Almacen => 'Almacén',
            self::Administracion => 'Administración',
        };
    }

    /**
     * El área de un usuario según su rol. Quien no tiene un rol base (o es
     * el admin) pide por Administración y puede corregirlo en el vale.
     */
    public static function deUsuario(User $usuario): self
    {
        $roles = $usuario->getRoleNames()->all();

        return match (true) {
            in_array(RolBase::Gerencia->value, $roles, true) => self::Gerencia,
            in_array(RolBase::Operaciones->value, $roles, true) => self::Operaciones,
            in_array(RolBase::Taller->value, $roles, true), in_array(RolBase::Mecanico->value, $roles, true) => self::Taller,
            in_array(RolBase::Abastecimiento->value, $roles, true) => self::Abastecimiento,
            in_array(RolBase::Contador->value, $roles, true) => self::Contabilidad,
            in_array(RolBase::Rrhh->value, $roles, true) => self::Rrhh,
            in_array(RolBase::Almacen->value, $roles, true) => self::Almacen,
            default => self::Administracion,
        };
    }
}
