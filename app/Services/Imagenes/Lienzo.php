<?php

namespace App\Services\Imagenes;

use GdImage;
use RuntimeException;

/**
 * Una imagen de aviso armada de arriba hacia abajo, bloque por bloque, con GD
 * y la tipografía de la app. Cada método dibuja un bloque a todo el ancho y
 * baja el cursor; al final `png()` recorta la imagen a lo que se usó.
 *
 * Los textos aceptan la negrita de WhatsApp (`*así*`), así las imágenes
 * reutilizan los mismos textos que ya se mandaban como mensaje.
 */
class Lienzo
{
    /** Tope de alto antes de recortar; un aviso nunca llega ni cerca. */
    private const ALTO_MAXIMO = 5000;

    /** GD mide la letra en puntos a 96 ppp: esto la pasa a píxeles. */
    private const PUNTOS_A_PIXELES = 96 / 72;

    private GdImage $imagen;

    private int $y = 0;

    /** @var array<string, int> */
    private array $colores = [];

    /**
     * @param  positive-int  $ancho
     */
    public function __construct(
        private readonly int $ancho = 1080,
        private readonly int $margen = 64,
    ) {
        $imagen = imagecreatetruecolor($ancho, self::ALTO_MAXIMO);

        if ($imagen === false) {
            throw new RuntimeException('No se pudo crear la imagen.');
        }

        $this->imagen = $imagen;
        imagealphablending($this->imagen, true);
        imagefilledrectangle($this->imagen, 0, 0, $ancho, self::ALTO_MAXIMO, $this->color('#ffffff'));
    }

    /**
     * El logo a la izquierda y, a la derecha, un texto corto (la fecha).
     */
    public function encabezado(string $logo, ?string $derecha = null): static
    {
        $alto = 76;
        $this->y += 48;

        $origen = @imagecreatefrompng($logo);

        if ($origen !== false) {
            $anchoLogo = (int) round(imagesx($origen) * $alto / imagesy($origen));
            imagecopyresampled($this->imagen, $origen, $this->margen, $this->y, 0, 0, $anchoLogo, $alto, imagesx($origen), imagesy($origen));
        }

        if ($derecha !== null) {
            $tamano = 22;
            $anchoTexto = $this->anchoDe($derecha, $tamano, Peso::SemiBold);
            $this->escribir(
                $derecha,
                $this->ancho - $this->margen - $anchoTexto,
                $this->y + (int) (($alto - $this->altoDeLinea($tamano)) / 2),
                $tamano,
                '#475569',
                Peso::SemiBold,
            );
        }

        $this->y += $alto + 40;

        return $this;
    }

    /** Una franja de color a todo el ancho con un título. */
    public function banda(string $titulo, string $fondo, string $colorTexto = '#ffffff', int $tamano = 30): static
    {
        $relleno = 34;
        $lineas = $this->partir($titulo, $tamano, Peso::Bold, $this->ancho - 2 * $this->margen);
        $alto = 2 * $relleno + count($lineas) * $this->altoDeLinea($tamano);

        imagefilledrectangle($this->imagen, 0, $this->y, $this->ancho, $this->y + $alto, $this->color($fondo));

        $y = $this->y + $relleno;

        foreach ($lineas as $linea) {
            $this->escribirLinea($linea, $this->margen, $y, $tamano, $colorTexto);
            $y += $this->altoDeLinea($tamano);
        }

        $this->y += $alto;

        return $this;
    }

    /**
     * Un dato que tiene que verse de lejos (la placa): etiqueta chica y el
     * valor en grande, dentro de un recuadro.
     */
    public function destacado(string $etiqueta, string $valor, string $acento = '#285b9f'): static
    {
        $this->y += 44;
        $alto = 190;

        $this->rectanguloRedondeado($this->margen, $this->y, $this->ancho - $this->margen, $this->y + $alto, 24, '#f1f5f9');
        imagefilledrectangle($this->imagen, $this->margen, $this->y + 24, $this->margen + 8, $this->y + $alto - 24, $this->color($acento));

        $this->escribir(mb_strtoupper($etiqueta), $this->margen + 44, $this->y + 30, 17, '#64748b', Peso::SemiBold);
        $this->escribir($valor, $this->margen + 44, $this->y + 70, 64, '#0f172a', Peso::Bold);

        $this->y += $alto;

        return $this;
    }

    /** Etiqueta chica arriba y el valor abajo; el valor se parte si no entra. */
    public function fila(string $etiqueta, string $valor): static
    {
        $this->y += 30;
        $this->escribir(mb_strtoupper($etiqueta), $this->margen, $this->y, 16, '#64748b', Peso::SemiBold);
        $this->y += $this->altoDeLinea(16) + 4;

        return $this->parrafo($valor, 28, '#0f172a', Peso::SemiBold, espacioAntes: 0);
    }

    /**
     * Texto corrido, partido en líneas. `*negrita*` como en WhatsApp.
     */
    public function parrafo(
        string $texto,
        int $tamano = 22,
        string $color = '#1e293b',
        Peso $peso = Peso::Regular,
        int $sangria = 0,
        int $espacioAntes = 18,
    ): static {
        $this->y += $espacioAntes;
        $anchoUtil = $this->ancho - 2 * $this->margen - $sangria;

        foreach ($this->partir($texto, $tamano, $peso, $anchoUtil) as $linea) {
            $this->escribirLinea($linea, $this->margen + $sangria, $this->y, $tamano, $color);
            $this->y += $this->altoDeLinea($tamano);
        }

        return $this;
    }

    /** @param  list<string>  $items */
    public function vinetas(array $items, int $tamano = 22): static
    {
        foreach ($items as $item) {
            $this->y += 12;
            $radio = 6;
            $centroY = $this->y + (int) ($this->altoDeLinea($tamano) / 2);
            imagefilledellipse($this->imagen, $this->margen + 12, $centroY, 2 * $radio, 2 * $radio, $this->color('#285b9f'));
            $this->parrafo($item, $tamano, sangria: 36, espacioAntes: 0);
        }

        return $this;
    }

    /** Un recuadro de color claro con un texto adentro (el flete, un aviso). */
    public function recuadro(string $titulo, string $valor, ?string $detalle, string $fondo, string $color): static
    {
        $this->y += 36;
        $relleno = 32;
        $alto = 2 * $relleno + $this->altoDeLinea(17) + 6 + $this->altoDeLinea(44) + ($detalle ? $this->altoDeLinea(20) + 4 : 0);

        $this->rectanguloRedondeado($this->margen, $this->y, $this->ancho - $this->margen, $this->y + $alto, 24, $fondo);

        $y = $this->y + $relleno;
        $this->escribir(mb_strtoupper($titulo), $this->margen + $relleno, $y, 17, $color, Peso::SemiBold);
        $y += $this->altoDeLinea(17) + 6;
        $this->escribir($valor, $this->margen + $relleno, $y, 44, '#0f172a', Peso::Bold);

        if ($detalle) {
            $y += $this->altoDeLinea(44) + 4;
            $this->escribir($detalle, $this->margen + $relleno, $y, 20, '#475569');
        }

        $this->y += $alto;

        return $this;
    }

    public function espacio(int $pixeles): static
    {
        $this->y += $pixeles;

        return $this;
    }

    /** El PNG recortado a lo que se dibujó. */
    public function png(): string
    {
        $alto = min($this->y, self::ALTO_MAXIMO);
        $final = imagecrop($this->imagen, ['x' => 0, 'y' => 0, 'width' => $this->ancho, 'height' => $alto]);

        if ($final === false) {
            throw new RuntimeException('No se pudo recortar la imagen.');
        }

        ob_start();
        imagepng($final, null, 6);

        return (string) ob_get_clean();
    }

    /**
     * Parte un texto con `*negrita*` en líneas que entren en el ancho. Cada
     * línea es una lista de tramos [texto, peso, pegado]: `pegado` va sin
     * espacio antes, como el «:» que sigue a una negrita.
     *
     * @return list<list<array{0: string, 1: Peso, 2: bool}>>
     */
    private function partir(string $texto, int $tamano, Peso $peso, int $anchoUtil): array
    {
        $palabras = [];
        $anteriorTerminaEnEspacio = true;

        foreach (explode('*', $texto) as $indice => $tramo) {
            $pesoTramo = $indice % 2 === 1 ? Peso::Bold : $peso;
            // Va pegado a lo anterior solo si no hay espacio ni al final del
            // tramo previo ni al comienzo de este («*no salgas*:»).
            $pegado = $palabras !== [] && ! $anteriorTerminaEnEspacio && ! preg_match('/^\s/u', $tramo);

            if ($tramo !== '') {
                $anteriorTerminaEnEspacio = (bool) preg_match('/\s$/u', $tramo);
            }

            foreach (preg_split('/\s+/u', trim($tramo)) ?: [] as $palabra) {
                if ($palabra !== '') {
                    $palabras[] = [$palabra, $pesoTramo, $pegado];
                    $pegado = false;
                }
            }
        }

        $lineas = [];
        $actual = [];
        $anchoActual = 0;

        foreach ($palabras as [$palabra, $pesoPalabra, $pegado]) {
            $anchoPalabra = $this->avance($palabra, $tamano, $pesoPalabra);
            $separacion = $pegado ? 0 : $this->avance(' ', $tamano, $pesoPalabra);
            $necesario = $actual === [] ? $anchoPalabra : $anchoActual + $separacion + $anchoPalabra;

            if ($actual !== [] && $necesario > $anchoUtil) {
                $lineas[] = $actual;
                $actual = [];
                $necesario = $anchoPalabra;
            }

            $actual[] = [$palabra, $pesoPalabra, $pegado];
            $anchoActual = $necesario;
        }

        if ($actual !== []) {
            $lineas[] = $actual;
        }

        return $lineas;
    }

    /**
     * Dibuja la línea en tramos del mismo peso, cada uno de una sola vez:
     * así FreeType pone los espacios y el interletrado de la fuente.
     *
     * @param  list<array{0: string, 1: Peso, 2: bool}>  $linea
     */
    private function escribirLinea(array $linea, int $x, int $y, int $tamano, string $color): void
    {
        $tramos = [];

        foreach ($linea as $indice => [$palabra, $peso, $pegado]) {
            $texto = ($indice > 0 && ! $pegado ? ' ' : '').$palabra;
            $ultimo = array_key_last($tramos);

            if ($ultimo !== null && $tramos[$ultimo][1] === $peso) {
                $tramos[$ultimo][0] .= $texto;
            } else {
                $tramos[] = [$texto, $peso];
            }
        }

        foreach ($tramos as [$texto, $peso]) {
            $this->escribir($texto, $x, $y, $tamano, $color, $peso);
            $x += $this->avance($texto, $tamano, $peso);
        }
    }

    /** Escribe con la parte de arriba de la línea en `$y` (no la base). */
    private function escribir(string $texto, int $x, int $y, int $tamano, string $color, Peso $peso = Peso::Regular): void
    {
        $base = $y + (int) round($tamano * self::PUNTOS_A_PIXELES);

        imagettftext($this->imagen, $tamano, 0, $x, $base, $this->color($color), $peso->archivo(), $texto);
    }

    private function anchoDe(string $texto, int $tamano, Peso $peso): int
    {
        $caja = imagettfbbox($tamano, 0, $peso->archivo(), $texto);

        return $caja === false ? 0 : $caja[2] - $caja[0];
    }

    /**
     * Cuánto avanza el cursor al escribir el texto, espacios incluidos. El
     * ancho de la caja de GD mide solo la tinta (un espacio suelto da cero),
     * así que se mide hasta una «n» de referencia y se le resta.
     */
    private function avance(string $texto, int $tamano, Peso $peso): int
    {
        $conReferencia = imagettfbbox($tamano, 0, $peso->archivo(), $texto.'n');
        $referencia = imagettfbbox($tamano, 0, $peso->archivo(), 'n');

        return $conReferencia === false || $referencia === false ? 0 : $conReferencia[2] - $referencia[2];
    }

    private function altoDeLinea(int $tamano): int
    {
        return (int) round($tamano * self::PUNTOS_A_PIXELES * 1.4);
    }

    private function rectanguloRedondeado(int $x1, int $y1, int $x2, int $y2, int $radio, string $hex): void
    {
        $color = $this->color($hex);

        imagefilledrectangle($this->imagen, $x1 + $radio, $y1, $x2 - $radio, $y2, $color);
        imagefilledrectangle($this->imagen, $x1, $y1 + $radio, $x2, $y2 - $radio, $color);

        foreach ([[$x1 + $radio, $y1 + $radio], [$x2 - $radio, $y1 + $radio], [$x1 + $radio, $y2 - $radio], [$x2 - $radio, $y2 - $radio]] as [$cx, $cy]) {
            imagefilledellipse($this->imagen, $cx, $cy, 2 * $radio, 2 * $radio, $color);
        }
    }

    private function color(string $hex): int
    {
        if (! isset($this->colores[$hex])) {
            [$r, $g, $b] = array_map(
                fn (string $par): int => max(0, min(255, (int) hexdec($par))),
                str_split(str_pad(ltrim($hex, '#'), 6, '0'), 2),
            );
            $this->colores[$hex] = (int) imagecolorallocate($this->imagen, $r, $g, $b);
        }

        return $this->colores[$hex];
    }
}
