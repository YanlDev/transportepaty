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
     *
     * Se identifica por `username` y no por correo: el correo es opcional en el
     * resto de las cuentas y acá era la clave de búsqueda.
     */
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['username' => config('transpaty.admin.username')],
            [
                'name' => config('transpaty.admin.name'),
                'email' => config('transpaty.admin.email'),
                'password' => config('transpaty.admin.password'),
            ],
        );

        // El correo del admin sale de la configuración, así que se da por bueno.
        // `email_verified_at` no es asignable en masa: se fuerza.
        $admin->forceFill(['email_verified_at' => now()])->save();

        $admin->syncRoles(['admin']);
    }
}
