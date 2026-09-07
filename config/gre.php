<?php

/*
|--------------------------------------------------------------------------
| Guía de Remisión Electrónica del Transportista
|--------------------------------------------------------------------------
|
| Datos del emisor y credenciales para emitir la GRE-T (tipo 31) ante SUNAT.
| Nada de esto vive en el código: el certificado digital y las credenciales
| son secretos, y el número de registro MTC cambia cuando se renueva.
|
*/

return [

    'emisor' => [
        'ruc' => env('GRE_RUC'),
        'razon_social' => env('GRE_RAZON_SOCIAL'),
        'nombre_comercial' => env('GRE_NOMBRE_COMERCIAL'),
        'direccion' => env('GRE_DIRECCION'),
        'ubigeo' => env('GRE_UBIGEO'),

        // Número de Registro MTC de la empresa de transportes. Distinto del
        // TUC, que es por vehículo y vive en `vehiculos.tuc`.
        'registro_mtc' => env('GRE_REGISTRO_MTC'),
    ],

    // Serie propia de la GRE-T. Las guías que hoy existen usan series EG..,
    // que son las que asigna el portal SOL; al emitir desde acá la serie es
    // nuestra y arranca en V001.
    'serie' => env('GRE_SERIE', 'V001'),

    // Certificado digital en PEM (clave privada + certificado concatenados).
    // Fuera del disco público: es la identidad tributaria de la empresa.
    'certificado' => env('GRE_CERTIFICADO', 'gre/certificado.pem'),

    'api' => [
        'client_id' => env('GRE_CLIENT_ID'),
        'client_secret' => env('GRE_CLIENT_SECRET'),
        'usuario_sol' => env('GRE_USUARIO_SOL'),
        'clave_sol' => env('GRE_CLAVE_SOL'),
    ],

    // Beta acepta el certificado de pruebas y no genera documentos válidos.
    'produccion' => (bool) env('GRE_PRODUCCION', false),

    // El token y el envío viven en hosts distintos. Se configuran por separado
    // porque los sandbox que emulan la API GRE sirven ambos desde el mismo
    // dominio, y ahí es donde se prueba antes de tener credenciales propias.
    'endpoints' => [
        'auth' => env('GRE_URL_AUTH', 'https://api-seguridad.sunat.gob.pe/v1'),
        'cpe' => env('GRE_URL_CPE', 'https://api.sunat.gob.pe/v1'),
    ],

];
