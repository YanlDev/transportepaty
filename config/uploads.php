<?php

use App\Models\Abastecimiento;
use App\Models\ConductorDocumento;
use App\Models\Factura;
use App\Models\IngresoAlmacen;
use App\Models\LecturaKilometraje;
use App\Models\OrdenTrabajo;
use App\Models\VehiculoDocumento;
use App\Models\Viaje;

return [

    /*
    |--------------------------------------------------------------------------
    | Fotos
    |--------------------------------------------------------------------------
    |
    | Toda foto que entra se guarda como JPEG: derecha (orientación EXIF
    | aplicada), sin metadatos y con el lado mayor recortado a `lado_maximo`.
    | El navegador ya la manda achicada con estos mismos números; el servidor
    | lo repite porque no puede fiarse de lo que llega. `max_kb` es el peso
    | máximo que acepta la validación, antes de comprimir.
    |
    | El HEIC del iPhone solo se acepta si Imagick lo sabe leer; si no, se
    | rechaza con un mensaje que lo dice.
    |
    | `perfiles` cambia el tamaño y la calidad para una colección concreta: un
    | comprobante de compra hay que poder leerlo, y pide más detalle que la
    | foto de una pieza. Si tocas estos números, cambia también los de
    | `resources/js/lib/comprimir-imagen.ts`.
    |
    */

    'imagenes' => [
        'lado_maximo' => 1920,
        'calidad' => 80,
        'max_kb' => 10240,
        'mimes' => ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'],

        'perfiles' => [
            'comprobante' => ['lado_maximo' => 2200, 'calidad' => 85],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | PDF
    |--------------------------------------------------------------------------
    |
    | Un PDF se guarda tal cual y después, en la cola, Ghostscript lo
    | recomprime. El original solo se reemplaza si el resultado es un PDF
    | válido y pesa al menos `ahorro_minimo` menos (0.10 = 10 %).
    |
    | `timeout` debe quedar por debajo del `retry_after` de la cola (90 s):
    | si no, la cola daría el trabajo por perdido y lo repetiría.
    |
    | `lote` es el límite de las pantallas que suben varios PDF de una (GR,
    | facturas): pocos KB por archivo para que el lote entero quepa en el
    | `post_max_size` del servidor.
    |
    */

    'pdf' => [
        'max_kb' => 10240,
        'comprimir' => (bool) env('UPLOADS_COMPRIMIR_PDF', true),
        'ahorro_minimo' => 0.10,
        'cola' => 'default',

        'ghostscript' => [
            'binario' => env('GHOSTSCRIPT_BIN', 'gs'),
            'ajuste' => '/ebook',
            'timeout' => 60,
        ],

        'lote' => [
            'max_archivos' => 20,
            'max_kb' => 2048,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | PDF que no se comprimen
    |--------------------------------------------------------------------------
    |
    | `modelos`: los PDF de estos modelos se dejan como llegaron. Las GR y las
    | facturas vienen de SUNAT, pesan unos KB y el sistema las lee para sacar
    | sus datos: recomprimirlas no ahorra nada y arriesga la lectura.
    |
    | `tipos_documento`: valores de `tipo` de un documento de vehículo o de
    | conductor que tampoco se tocan (por ejemplo un contrato firmado, donde
    | importa conservar el escaneo exacto). Usa los valores de los enums
    | TipoDocumento / TipoDocumentoConductor.
    |
    */

    'exclusiones' => [
        'modelos' => [
            Viaje::class,
            Factura::class,
        ],
        'tipos_documento' => [
            //
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Carpetas por módulo
    |--------------------------------------------------------------------------
    |
    | Cada archivo se guarda en `{módulo}/{año}/{mes}/{uuid}/{uuid}.ext`. Un
    | modelo que no esté en esta lista cae en `otros`.
    |
    */

    'modulos' => [
        Viaje::class => 'viajes',
        Factura::class => 'facturas',
        VehiculoDocumento::class => 'vehiculos',
        ConductorDocumento::class => 'conductores',
        Abastecimiento::class => 'abastecimiento',
        LecturaKilometraje::class => 'kilometraje',
        OrdenTrabajo::class => 'mantenimiento',
        IngresoAlmacen::class => 'almacen',
    ],

];
