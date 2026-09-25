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
        'username' => env('ADMIN_USERNAME', 'admin'),
        'email' => env('ADMIN_EMAIL', 'admin@transpaty.com'),
        'name' => env('ADMIN_NAME', 'Admin Transpaty'),
        'password' => env('ADMIN_PASSWORD', 'transpaty2026'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Operaciones
    |--------------------------------------------------------------------------
    |
    | El WhatsApp que recibe el resumen de salidas del día y el teléfono de
    | oficina que se le da al conductor en el preaviso, para que llame en vez
    | de avanzar sin guía de remisión. Ambos son opcionales: sin ellos el
    | aviso al conductor sigue saliendo, solo que sin número al que llamar.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Áreas que reciben aviso de una programación
    |--------------------------------------------------------------------------
    |
    | Abastecimiento necesita saber qué unidad sale y a dónde para preparar la
    | carga; facturación, además, con qué flete se acordó. Cada una recibe su
    | propio mensaje: los montos no viajan al WhatsApp del patio.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | IGV
    |--------------------------------------------------------------------------
    |
    | La tasa con la que se calcula el importe con IGV de un flete acordado.
    | Vive acá y no como número suelto en el código porque es un parámetro del
    | Estado, no una decisión de la aplicación.
    |
    */

    'igv' => (float) env('TRANSPATY_IGV', 0.18),

    'areas' => [
        'abastecimiento' => env('TRANSPATY_WHATSAPP_ABASTECIMIENTO'),
        'facturacion' => env('TRANSPATY_WHATSAPP_FACTURACION'),
    ],

    'operaciones' => [
        'whatsapp' => env('TRANSPATY_WHATSAPP_OPERACIONES'),
        'telefono_oficina' => env('TRANSPATY_TELEFONO_OFICINA'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Servicio de WhatsApp
    |--------------------------------------------------------------------------
    |
    | El proceso Node de `whatsapp/` (Baileys) que manda los mensajes con el
    | número de la empresa. Escucha solo en el propio servidor y exige el
    | mismo token que tiene en su entorno.
    |
    */

    'whatsapp' => [
        'url' => env('WHATSAPP_SERVICIO_URL', 'http://127.0.0.1:3100'),
        'token' => env('WHATSAPP_SERVICIO_TOKEN'),
    ],

];
