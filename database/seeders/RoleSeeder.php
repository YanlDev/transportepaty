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
     * - contador: solo la cobranza —facturas, pagos y cuentas de la empresa—
     *   más los viajes en lectura, que es contra lo que se factura.
     */
    public function run(): void
    {
        foreach (['admin', 'conductor', 'visor', 'contador'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }
}
