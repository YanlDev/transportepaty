<?php

namespace App\Services;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;

/**
 * Un código QR como cuadrícula de celdas (true = negra), sin imagen: el PDF
 * lo dibuja como una tabla y la pantalla como un SVG, y así no depende de
 * ninguna extensión de imágenes del servidor.
 *
 * Usa `bacon/bacon-qr-code`, que ya viene instalado con Fortify (es el que
 * arma el QR de la verificación en dos pasos).
 */
class CodigoQr
{
    /**
     * @return list<list<bool>>
     */
    public function celdas(string $contenido): array
    {
        $matriz = Encoder::encode($contenido, ErrorCorrectionLevel::M())->getMatrix();
        $celdas = [];

        for ($y = 0; $y < $matriz->getHeight(); $y++) {
            $fila = [];

            for ($x = 0; $x < $matriz->getWidth(); $x++) {
                $fila[] = $matriz->get($x, $y) === 1;
            }

            $celdas[] = $fila;
        }

        return $celdas;
    }
}
