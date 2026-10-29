<?php

namespace App\Services\Imagenes;

use Carbon\CarbonImmutable;

/**
 * Los avisos por WhatsApp que van como imagen, con el logo y los colores de
 * la empresa. Acá solo se decide cómo se ven: los datos llegan armados.
 */
class ImagenesDeAviso
{
    private const AZUL = '#285b9f';

    private const ROJO = '#b91c1c';

    /**
     * El recordatorio a las áreas: los tickets que ya debían salir y siguen
     * sin GR, uno por fila.
     *
     * @param  iterable<array{placa: string, detalle: string}>  $sinGr
     */
    public function recordatorioSinGr(\DateTimeInterface $fecha, iterable $sinGr): string
    {
        $lienzo = $this->lienzo($fecha)->banda('UNIDADES SIN GR', self::ROJO);
        $cantidad = 0;

        foreach ($sinGr as $fila) {
            $cantidad++;
            $lienzo->filaUnidad($fila['placa'], $fila['detalle']);
        }

        return $lienzo
            ->espacio(40)
            ->banda(
                $cantidad === 1
                    ? '1 unidad programada sigue sin GR: no puede salir hasta emitirla.'
                    : "{$cantidad} unidades programadas siguen sin GR: ninguna puede salir hasta emitirla.",
                self::AZUL,
                tamano: 22,
            )
            ->png();
    }

    private function lienzo(\DateTimeInterface $fecha): Lienzo
    {
        return (new Lienzo)->encabezado(
            public_path('marca/logo-horizontal.png'),
            ucfirst(CarbonImmutable::instance($fecha)->settings(['locale' => 'es'])->isoFormat('dddd D [de] MMMM')),
        );
    }
}
