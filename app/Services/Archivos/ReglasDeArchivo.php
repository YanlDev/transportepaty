<?php

namespace App\Services\Archivos;

/**
 * Las reglas de validación de un archivo subido, armadas desde
 * `config/uploads.php` para que todos los formularios acepten lo mismo.
 *
 * Usan `mimetypes`, que mira el contenido del archivo y no su extensión: un
 * ejecutable renombrado a `.jpg` no pasa.
 */
class ReglasDeArchivo
{
    /**
     * @return list<string>
     */
    public static function imagen(): array
    {
        return ['file', 'mimetypes:'.implode(',', self::mimesDeImagen()), 'max:'.config('uploads.imagenes.max_kb')];
    }

    /**
     * @return list<string>
     */
    public static function pdf(): array
    {
        return ['file', 'mimetypes:application/pdf', 'max:'.config('uploads.pdf.max_kb')];
    }

    /**
     * Un PDF de una subida de varios a la vez (GR, facturas).
     *
     * @return list<string>
     */
    public static function pdfDeLote(): array
    {
        return ['file', 'mimetypes:application/pdf', 'max:'.config('uploads.pdf.lote.max_kb')];
    }

    /**
     * @return list<string>
     */
    public static function imagenOPdf(): array
    {
        $maximo = max((int) config('uploads.imagenes.max_kb'), (int) config('uploads.pdf.max_kb'));

        return ['file', 'mimetypes:'.implode(',', [...self::mimesDeImagen(), 'application/pdf']), 'max:'.$maximo];
    }

    /**
     * @return list<string>
     */
    public static function mimesDeImagen(): array
    {
        return array_values((array) config('uploads.imagenes.mimes'));
    }
}
