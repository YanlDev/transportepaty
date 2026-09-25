<?php

namespace App\Enums;

use App\Models\Programacion;

/**
 * Los avisos que se mandan por una salida programada, cada uno como imagen.
 * Los dos que van al conductor son los que respaldan a la empresa ante una
 * multa, y por eso son los únicos que marcan la salida como avisada.
 */
enum TipoAvisoSalida: string
{
    case Conductor = 'conductor';
    case Advertencia = 'advertencia';
    case Abastecimiento = 'abastecimiento';
    case Facturacion = 'facturacion';

    public function marcaComoAvisado(): bool
    {
        return in_array($this, [self::Conductor, self::Advertencia], true);
    }

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
            self::Abastecimiento => "Unidad programada {$fecha} · {$placa}",
            self::Facturacion => "Unidad programada {$fecha} · {$placa} · flete",
        };
    }
}
