<?php

use App\Services\Archivos\GeneradorDeRutas;

/*
| Solo lo que cambia respecto de la configuración del paquete (el resto se
| hereda): dónde cae cada archivo dentro del disco. Ver `config/uploads.php`.
*/

return [

    'path_generator' => GeneradorDeRutas::class,

];
