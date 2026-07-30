<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    /**
     * Cuenta administradora de Transpaty.
     *
     * Se recrea en cada `migrate:fresh --seed` para que el sistema nunca quede
     * sin acceso. La contraseña sale de ADMIN_PASSWORD; el valor por defecto
     * solo sirve para desarrollo y debe cambiarse antes de salir a producción.
     */
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => config('transpaty.admin.email')],
            [
                'name' => config('transpaty.admin.name'),
                'password' => config('transpaty.admin.password'),
            ],
        );

        // `email_verified_at` no es asignable en masa, pero las rutas están tras
        // el middleware `verified`: sin esto el admin entra y queda bloqueado.
        $admin->forceFill(['email_verified_at' => now()])->save();

        $admin->syncRoles(['admin']);
    }
}
