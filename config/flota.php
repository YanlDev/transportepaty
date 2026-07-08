<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Días de reposo
    |--------------------------------------------------------------------------
    |
    | Umbral de días sin registro de movimiento (combustible o kilometraje)
    | a partir del cual una unidad se considera "en reposo" y candidata a una
    | activación periódica (Subproceso 3 del Manual de Gestión de Flota).
    |
    */

    'reposo_dias' => (int) env('FLOTA_REPOSO_DIAS', 7),

];
