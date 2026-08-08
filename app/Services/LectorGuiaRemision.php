<?php

namespace App\Services;

use Smalot\PdfParser\Page;
use Smalot\PdfParser\Parser;

/**
 * Lee el PDF de una Guía de Remisión Electrónica (GRE) de SUNAT — la
 * representación impresa que emite la propia empresa como transportista — y
 * devuelve sus campos como texto crudo, sin resolver contra el padrón (eso lo
 * hace quien use este lector).
 *
 * El texto que entrega `PDFObject::getText()` de la librería sale del orden en
 * que el PDF define los objetos, no del orden visual: etiqueta y valor pueden
 * terminar en extremos opuestos del documento. Por eso acá se reconstruye el
 * texto usando las coordenadas de cada fragmento (`Page::getDataTm()`) —
 * agrupando por línea (Y) y ordenando dentro de la línea por columna (X) — el
 * mismo principio que usa `pdftotext -layout`. Verificado contra PDFs reales
 * de distintos clientes (Minsur, Ceramica San Lorenzo): con el texto
 * reconstruido, cada etiqueta queda siempre pegada a su valor.
 */
class LectorGuiaRemision
{
    /**
     * Diferencia de Y, en unidades del PDF, dentro de la cual dos fragmentos
     * se consideran de la misma línea visual.
     */
    private const TOLERANCIA_LINEA = 3.0;

    /**
     * Campos crudos, sin resolver contra el padrón. Todos son `string|null`
     * salvo `guias_remitente`, que es `list<array{numero: string, ruc:
     * string}>` (puede venir vacía). Las claves son: `numero_gr`,
     * `fecha_emision`, `fecha_traslado`, `origen`, `destino`, `cliente`,
     * `cliente_ruc`, `destinatario`, `destinatario_ruc`, `subcontratador`,
     * `subcontratador_ruc`, `guias_remitente`, `peso`, `unidad_peso`,
     * `placa_tracto`, `placa_carreta`, `conductor_nombre`, `conductor_dni`.
     *
     * @return array<string, mixed>
     */
    public function extraerDesdeArchivo(string $ruta): array
    {
        $documento = (new Parser)->parseFile($ruta);
        $texto = implode("\n", array_map(
            fn (Page $pagina): string => $this->reconstruirTexto($pagina),
            $documento->getPages(),
        ));

        return $this->extraerCampos($texto);
    }

    /**
     * Reconstruye el texto de una página en orden de lectura visual: agrupa
     * los fragmentos por línea (mismo Y, con tolerancia) y los ordena de
     * izquierda a derecha (X) dentro de cada línea.
     */
    private function reconstruirTexto(Page $pagina): string
    {
        $fragmentos = [];

        foreach ($pagina->getDataTm() as [$matriz, $contenido]) {
            if (trim($contenido) === '') {
                continue;
            }

            $fragmentos[] = ['x' => (float) $matriz[4], 'y' => (float) $matriz[5], 'texto' => $contenido];
        }

        usort($fragmentos, function (array $uno, array $otro): int {
            if (abs($uno['y'] - $otro['y']) > self::TOLERANCIA_LINEA) {
                return $otro['y'] <=> $uno['y'];
            }

            return $uno['x'] <=> $otro['x'];
        });

        $lineas = [];
        $yLineaActual = null;

        foreach ($fragmentos as $fragmento) {
            if ($yLineaActual === null || abs($fragmento['y'] - $yLineaActual) > self::TOLERANCIA_LINEA) {
                $lineas[] = [];
                $yLineaActual = $fragmento['y'];
            }

            $lineas[count($lineas) - 1][] = $fragmento['texto'];
        }

        return implode("\n", array_map(
            fn (array $linea): string => implode(' ', $linea),
            $lineas,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function extraerCampos(string $texto): array
    {
        return [
            'numero_gr' => $this->normalizarNumero($this->capturar('/N°\s*([A-Z0-9]+\s*-\s*\d+)/u', $texto)),
            'fecha_emision' => $this->capturar('/Fecha y hora de emisión:\s*(\d{2}\/\d{2}\/\d{4}\s+\d{2}:\d{2}\s*[AP]M)/u', $texto),
            'fecha_traslado' => $this->capturar('/Fecha de inicio de Traslado:\s*(\d{2}\/\d{2}\/\d{4})/u', $texto),
            'origen' => $this->capturarBloque('Punto de Partida:', 'Punto de llegada:', $texto),
            'destino' => $this->capturarBloque('Punto de llegada:', 'Datos del remitente:', $texto),
            ...$this->extraerEmpresa('cliente', 'Datos del remitente:', $texto),
            ...$this->extraerEmpresa('destinatario', 'Datos del destinatario:', $texto),
            // Cuando el flete lo paga un subcontratador, el vínculo comercial
            // real de Paty es con él, no con el remitente que figura en la
            // GR: quien use estos campos decide si lo usa para reemplazar al
            // cliente (ver `ImportadorViaje`).
            ...$this->extraerEmpresa('subcontratador', 'Datos del subcontratador:', $texto),
            'guias_remitente' => $this->extraerGuiasRemitente($texto),
            'peso' => $this->capturar('/Peso Bruto total de la carga:\s*([\d,]+(?:\.\d+)?)/u', $texto),
            'unidad_peso' => $this->capturar('/Unidad de Medida del Peso Bruto:\s*(\w+)/u', $texto),
            'placa_tracto' => $this->capturar('/Principal:\s*Número de placa:\s*([A-Z0-9]+)/u', $texto),
            'placa_carreta' => $this->capturar('/Secundario 1:\s*Número de placa:\s*([A-Z0-9]+)/u', $texto),
            ...$this->extraerConductor($texto),
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function extraerEmpresa(string $clave, string $etiqueta, string $texto): array
    {
        // El corte de línea del PDF puede caer justo antes de «N°» en vez de
        // después (visto cuando remitente y destinatario son la misma
        // empresa): el espacio entre «CONTRIBUYENTES» y «N°» tiene que admitir
        // un salto de línea, no solo un espacio literal.
        $patron = '/'.preg_quote($etiqueta, '/').'\s*(.*?)\s*-\s*REGISTRO ÚNICO DE CONTRIBUYENTES\s*N°\s*(\d+)/us';

        if (! preg_match($patron, $texto, $coincidencias)) {
            return [$clave => null, "{$clave}_ruc" => null];
        }

        return [
            $clave => trim($coincidencias[1]),
            "{$clave}_ruc" => $coincidencias[2],
        ];
    }

    /**
     * Puede haber más de una guía de remisión del remitente referenciada
     * (visto en documentos que consolidan varios despachos del cliente).
     *
     * @return list<array{numero: string, ruc: string}>
     */
    private function extraerGuiasRemitente(string $texto): array
    {
        // Puede traer un sufijo como «- FÍSICO» cuando la guía referenciada del
        // remitente no es electrónica.
        if (! preg_match_all('/Guía de Remisión Remitente(?:-\s*\S+)?\s*N°\s*(.+?)\s*-\s*RUC N°\s*(\d+)/u', $texto, $coincidencias, PREG_SET_ORDER)) {
            return [];
        }

        return array_map(
            fn (array $coincidencia): array => [
                'numero' => trim($coincidencia[1]),
                'ruc' => $coincidencia[2],
            ],
            $coincidencias,
        );
    }

    /**
     * El conductor «Principal» aparece dos veces en el documento: en «Datos de
     * los vehículos» no existe, pero el rótulo «Principal:» sí se repite ahí
     * para la placa. Se acota la búsqueda a la sección de conductores para no
     * cruzarse con eso.
     *
     * @return array{conductor_nombre: string|null, conductor_dni: string|null}
     */
    private function extraerConductor(string $texto): array
    {
        // El bloque «Datos de pagador de flete» no siempre existe (falta
        // cuando el pagador es un tercero), así que se acota contra el pie de
        // página de SUNAT, que sí está siempre presente.
        $seccion = $this->capturarBloque(
            'Datos de los conductores:',
            'Esta es una representación impresa',
            $texto,
        ) ?? '';

        if (! preg_match('/Principal:\s*(.*?)\s*-\s*DOCUMENTO NACIONAL DE IDENTIDAD N°\s*(\d+)/us', $seccion, $coincidencias)) {
            return ['conductor_nombre' => null, 'conductor_dni' => null];
        }

        return [
            'conductor_nombre' => trim($coincidencias[1]),
            'conductor_dni' => $coincidencias[2],
        ];
    }

    private function capturar(string $patron, string $texto): ?string
    {
        if (! preg_match($patron, $texto, $coincidencias)) {
            return null;
        }

        return trim($coincidencias[1]);
    }

    /**
     * Texto entre dos etiquetas, con los saltos de línea de las direcciones
     * envueltas colapsados a un solo espacio.
     */
    private function capturarBloque(string $desde, string $hasta, string $texto): ?string
    {
        $patron = '/'.preg_quote($desde, '/').'\s*(.*?)\s*'.preg_quote($hasta, '/').'/us';

        if (! preg_match($patron, $texto, $coincidencias)) {
            return null;
        }

        return trim(preg_replace('/\s+/u', ' ', $coincidencias[1]));
    }

    /**
     * «EG03 - 00011965» → «EG03-00011965».
     */
    private function normalizarNumero(?string $numero): ?string
    {
        if ($numero === null) {
            return null;
        }

        return preg_replace('/\s*-\s*/', '-', $numero);
    }
}
