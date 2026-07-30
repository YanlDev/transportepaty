<?php

namespace Tests\Support;

use ZipArchive;

/**
 * Arma un .xlsx mínimo pero válido para probar el lector sin depender de
 * ninguna librería de escritura: el mismo ZIP con XML adentro que se lee en
 * producción, solo que aquí se construye a mano fila por fila.
 */
final class ConstructorXlsx
{
    /**
     * @param  array<int, array<string, string>>  $filas  Número de fila => [letra columna => valor].
     */
    public static function crear(string $ruta, array $filas): void
    {
        $compartidas = [];
        $indice = [];

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        foreach ($filas as $numero => $celdas) {
            $sheetXml .= "<row r=\"{$numero}\">";

            foreach ($celdas as $letra => $valor) {
                if (! isset($indice[$valor])) {
                    $indice[$valor] = count($compartidas);
                    $compartidas[] = $valor;
                }

                $ref = "{$letra}{$numero}";
                $sheetXml .= "<c r=\"{$ref}\" t=\"s\"><v>{$indice[$valor]}</v></c>";
            }

            $sheetXml .= '</row>';
        }

        $sheetXml .= '</sheetData></worksheet>';

        $sharedXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.count($compartidas).'" uniqueCount="'.count($compartidas).'">'
            .implode('', array_map(fn (string $texto): string => '<si><t>'.htmlspecialchars($texto, ENT_XML1).'</t></si>', $compartidas))
            .'</sst>';

        $zip = new ZipArchive;
        $zip->open($ruta, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
            .'</Types>');

        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');

        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Hoja1" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>');

        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
            .'</Relationships>');

        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->addFromString('xl/sharedStrings.xml', $sharedXml);

        $zip->close();
    }
}
