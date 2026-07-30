<?php

namespace App\Services;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Lee un .xlsx sin depender de ninguna librería: un Excel moderno es un ZIP con
 * XML adentro, y PHP ya trae todo lo necesario para abrirlo. Evita sumar una
 * dependencia pesada al proyecto solo para leer una planilla con un formato ya
 * conocido.
 *
 * Detecta la cabecera buscando la fila que contiene «CODIGO», tal como pide el
 * reporte real, en vez de asumir que siempre está en la misma fila.
 */
final class LectorExcelDisponibilidad
{
    private const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    private const NS_RELS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    /**
     * Filas que se examinan buscando la cabecera antes de rendirse.
     */
    private const LIMITE_BUSQUEDA_CABECERA = 10;

    public function leer(string $ruta): HojaExcelLeida
    {
        $zip = new ZipArchive;

        if ($zip->open($ruta) !== true) {
            throw new RuntimeException('El archivo no se pudo abrir. ¿Es un .xlsx válido?');
        }

        try {
            $compartidas = $this->leerCadenasCompartidas($zip);
            $hoja = $this->primeraHoja($zip);

            return $this->parsear($zip, $hoja, $compartidas);
        } finally {
            $zip->close();
        }
    }

    /**
     * @return list<string>
     */
    private function leerCadenasCompartidas(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if ($xml === false) {
            return [];
        }

        $raiz = new SimpleXMLElement($xml);
        $raiz->registerXPathNamespace('s', self::NS);

        $cadenas = [];

        foreach ($raiz->xpath('//s:si') ?: [] as $si) {
            $si->registerXPathNamespace('s', self::NS);
            // Concatena todos los fragmentos <t>: un texto con formato mixto
            // (parte en negrita, parte no) llega partido en varios <r><t>.
            $textos = $si->xpath('.//s:t') ?: [];
            $cadenas[] = implode('', array_map(fn (SimpleXMLElement $t): string => (string) $t, $textos));
        }

        return $cadenas;
    }

    /**
     * Ruta interna de la primera hoja del libro. Los reportes reales traen una
     * sola hoja relevante; cuando llegan varias, se lee la primera y quien
     * confirme la importación decide qué hacer con el resto.
     */
    private function primeraHoja(ZipArchive $zip): string
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($workbook === false || $rels === false) {
            throw new RuntimeException('El archivo no tiene la estructura de un libro de Excel.');
        }

        $raizRels = new SimpleXMLElement($rels);
        $destinos = [];

        foreach ($raizRels->Relationship as $relacion) {
            $destinos[(string) $relacion['Id']] = (string) $relacion['Target'];
        }

        $raizWorkbook = new SimpleXMLElement($workbook);
        $raizWorkbook->registerXPathNamespace('s', self::NS);
        $raizWorkbook->registerXPathNamespace('r', self::NS_RELS);

        $primeraHoja = $raizWorkbook->xpath('//s:sheets/s:sheet')[0] ?? null;

        if ($primeraHoja === null) {
            throw new RuntimeException('El libro de Excel no tiene ninguna hoja.');
        }

        $id = (string) $primeraHoja->attributes(self::NS_RELS)->id;
        $destino = ltrim($destinos[$id], '/');

        return str_starts_with($destino, 'xl/') ? $destino : "xl/{$destino}";
    }

    /**
     * @param  list<string>  $compartidas
     */
    private function parsear(ZipArchive $zip, string $hoja, array $compartidas): HojaExcelLeida
    {
        $xml = $zip->getFromName($hoja);

        if ($xml === false) {
            throw new RuntimeException('No se pudo leer la hoja de datos del archivo.');
        }

        $raiz = new SimpleXMLElement($xml);
        $raiz->registerXPathNamespace('s', self::NS);

        $filas = [];

        foreach ($raiz->xpath('//s:sheetData/s:row') ?: [] as $row) {
            $numero = (int) $row['r'];
            $celdas = [];

            foreach ($row->c as $celda) {
                $letra = $this->letraDeColumna((string) $celda['r']);
                $valor = $this->valorDeCelda($celda, $compartidas);

                if ($valor !== null && $valor !== '') {
                    $celdas[$letra] = $valor;
                }
            }

            if ($celdas !== []) {
                $filas[$numero] = $celdas;
            }
        }

        $filaCabecera = $this->detectarFilaCabecera($filas);
        $columnas = $filas[$filaCabecera] ?? [];

        return new HojaExcelLeida($filaCabecera, $columnas, $filas);
    }

    /**
     * @param  list<string>  $compartidas
     */
    private function valorDeCelda(SimpleXMLElement $celda, array $compartidas): ?string
    {
        $tipo = (string) $celda['t'];

        if ($tipo === 'inlineStr') {
            return (string) ($celda->is->t ?? '');
        }

        $v = $celda->v;

        if (! isset($v) || (string) $v === '') {
            return null;
        }

        if ($tipo === 's') {
            return $compartidas[(int) $v] ?? null;
        }

        return (string) $v;
    }

    /**
     * La fila que contiene «CODIGO» en alguna de sus celdas. No se asume que
     * esté siempre en la misma posición: el reporte real la trae en la fila 2,
     * pero exigirlo a rajatabla rompería el lector ante cualquier fila extra que
     * alguien agregue arriba.
     *
     * @param  array<int, array<string, string>>  $filas
     */
    private function detectarFilaCabecera(array $filas): int
    {
        $numeros = array_slice(array_keys($filas), 0, self::LIMITE_BUSQUEDA_CABECERA);

        foreach ($numeros as $numero) {
            foreach ($filas[$numero] as $valor) {
                if (mb_strtoupper(trim($valor)) === 'CODIGO') {
                    return $numero;
                }
            }
        }

        throw new RuntimeException(
            'No se encontró la columna «CODIGO» en las primeras filas del archivo. '.
            '¿Es el reporte de disponibilidad correcto?',
        );
    }

    private function letraDeColumna(string $referencia): string
    {
        return preg_replace('/\d+/', '', $referencia) ?? $referencia;
    }
}
