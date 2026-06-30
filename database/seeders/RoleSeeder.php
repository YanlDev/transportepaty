<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Roles base de la aplicación de flota vehicular.
     *
     * - admin: gestiona todo el sistema.
     * - conductor: usuario con login que ve sus vehículos asignados y registra recargas.
     * - visor: solo lectura.
     */
    public function run(): void
    {
        foreach (['admin', 'conductor', 'visor'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }
}
