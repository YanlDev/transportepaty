<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cuenta administradora
    |--------------------------------------------------------------------------
    |
    | Credenciales que AdminSeeder usa para recrear la cuenta admin en cada
    | `migrate:fresh --seed`. Se leen aquí y no con env() directo en el seeder
    | porque con la configuración cacheada env() devuelve null.
    |
    */

    'admin' => [
        'email' => env('ADMIN_EMAIL', 'admin@transpaty.com'),
        'name' => env('ADMIN_NAME', 'Admin Transpaty'),
        'password' => env('ADMIN_PASSWORD', 'transpaty2026'),
    ],

];
