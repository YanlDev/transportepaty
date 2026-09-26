<?php

namespace App\Enums;

use App\Models\Programacion;

/**
 * Los avisos al conductor de una salida programada, cada uno como imagen. Son
 * los que respaldan a la empresa ante una multa, y por eso marcan la salida
 * como avisada. Los de las áreas de la casa van aparte (ver `AreaAviso`).
 */
enum TipoAvisoSalida: string
{
    case Conductor = 'conductor';
    case Advertencia = 'advertencia';

    /**
     * La leyenda corta que acompaña a la imagen: es lo que se lee en la
     * notificación del celular antes de abrir el chat.
     */
    public function leyenda(Programacion $programacion): string
    {
        $fecha = $programacion->fecha->format('d/m');
        $placa = $programacion->vehiculo->placa;

        return match ($this) {
            self::Conductor => "Programación {$fecha} · {$placa}",
            self::Advertencia => 'Documentación obligatoria antes de salir',
        };
    }
}
