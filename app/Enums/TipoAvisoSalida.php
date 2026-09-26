<?php

namespace App\Enums;

/**
 * Los avisos al conductor de una salida programada, cada uno como imagen. Son
 * los que respaldan a la empresa ante una multa, y por eso marcan la salida
 * como avisada. Los de las áreas de la casa van aparte (ver `AreaAviso`).
 */
enum TipoAvisoSalida: string
{
    case Conductor = 'conductor';
    case Advertencia = 'advertencia';
}
