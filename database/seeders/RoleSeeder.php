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
     * - visor: solo lectura de la operación.
     * - contador: solo la cobranza —facturas, pagos y cuentas de la empresa—
     *   más los viajes y el padrón de unidades en lectura, que es contra lo
     *   que se factura.
     *
     * Hubo un rol `conductor` para que un chofer consultara su unidad. Se
     * quitó porque nunca tuvo pantalla propia: el login manda al tablero y ese
     * rol no lo podía ver, así que su único destino posible era un 403. Vuelve
     * cuando exista la vista del chofer.
     */
    public function run(): void
    {
        foreach (['admin', 'visor', 'contador'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }
}
