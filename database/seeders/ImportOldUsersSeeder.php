<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ImportOldUsersSeeder extends Seeder
{
    /**
     * Usuarios recuperados de la app piloto (BD `flotavselcosi`).
     *
     * Los hashes bcrypt se conservan TAL CUAL para que entren con su contraseña
     * actual. Se insertan con `DB::table` para evitar el cast `hashed` del modelo
     * User, que volvería a hashear el hash existente y rompería el login.
     */
    public function run(): void
    {
        /** @var list<array{name: string, email: string, password: string, rol: string}> $usuarios */
        $usuarios = [
            [
                'name' => 'Milder Carreón',
                'email' => 'yanivcc123@gmail.com',
                'password' => '$2y$12$wJzeXVBg93o1JvAYbUVDfeA.16jTAGJV7nrat7x.RxJ1wwUcQ3Q4y',
                'rol' => 'admin',
            ],
            [
                'name' => 'Hipolito Larico Mamani',
                'email' => 'hlarico@selcosixport.com',
                'password' => '$2y$12$/UkFPz0pmZJM20gO9DTmVOYWIklxdx.TAHmK6Ex4tTcQw25OZlSq2',
                'rol' => 'visor',
            ],
            [
                'name' => 'JOHN QUISPE VILCA',
                'email' => 'johnb_v@hotmail.com',
                'password' => '$2y$12$xpcZ8GbNyqHO6dbaF4n2yu5c4Ksa6tZ94oq8U6s6tVs7KZuBwnJXm',
                'rol' => 'conductor',
            ],
            [
                'name' => 'Rubén Lozano tapullima',
                'email' => 'jlozano@selcosiexportgold.com',
                'password' => '$2y$12$P5zhyn6pIYQLd/8pGTW8/.QSYtvbNt5ET87SogZS4iRjQuZXQTsyq',
                'rol' => 'conductor',
            ],
        ];

        foreach ($usuarios as $usuario) {
            DB::table('users')->updateOrInsert(
                ['email' => $usuario['email']],
                [
                    'name' => $usuario['name'],
                    'password' => $usuario['password'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );

            User::where('email', $usuario['email'])
                ->first()
                ?->syncRoles([$usuario['rol']]);
        }
    }
}
