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
        if ($this->esBienesFiscalizados($texto)) {
            return $this->extraerCamposBienesFiscalizados($texto);
        }

        // La GRE Remitente (la emite el propio dueño de la carga, no Paty) no
        // trae sección «Datos del remitente:» —el remitente es quien genera
        // el documento— así que el cliente real se saca de la razón social
        // que encabeza el PDF, y el bloque de destino corta contra «Datos del
        // Destinatario» en vez de esa sección inexistente.
        $esRemitente = (bool) preg_match('/GUÍA DE REMISIÓN ELECTRÓNICA\R+REMITENTE\b/u', $texto);

        return [
            'numero_gr' => $this->normalizarNumero($this->capturar('/N°\s*([A-Z0-9]+\s*-\s*\d+)/u', $texto)),
            'fecha_emision' => $this->capturar('/Fecha y hora de emisión\s*:\s*(\d{2}\/\d{2}\/\d{4}\s+\d{2}:\d{2}\s*[AP]M)/u', $texto),
            'fecha_traslado' => $this->capturar('/Fecha de inicio de Traslado\s*:\s*(\d{2}\/\d{2}\/\d{4})/u', $texto),
            // En la GRE Remitente, «Punto de Partida» comparte fila con la
            // columna izquierda (fechas, motivo de traslado): esas etiquetas
            // también cuentan como corte para no arrastrarlas al origen.
            'origen' => $this->capturarBloque(
                'Punto de Partida',
                $esRemitente
                    ? ['Fecha de inicio de Traslado', 'Motivo de Traslado', 'Punto de llegada']
                    : 'Punto de llegada',
                $texto,
            ),
            'destino' => $this->capturarBloque(
                'Punto de llegada',
                $esRemitente ? 'Datos del Destinatario' : 'Datos del remitente',
                $texto,
            ),
            ...($esRemitente
                ? $this->extraerEmisor($texto)
                : $this->extraerEmpresa('cliente', 'Datos del remitente:', $texto)),
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
     * La GRE de Bienes Fiscalizados (hidrocarburos, explosivos y similares,
     * bajo control de SUCAMEC/DGH) es un formato de SUNAT del todo distinto
     * al de la GRE Transportista/Remitente estándar: encabezados propios,
     * sin peso —viaja por unidades reguladas, no por KG/TN— y una tabla de
     * vehículos («DATOS DEL TRANSPORTE») en vez de tracto+carreta sueltos.
     */
    private function esBienesFiscalizados(string $texto): bool
    {
        return (bool) preg_match('/ELECTR[ÓO]NICA BF/u', $texto);
    }

    /**
     * Verificado contra el único documento de este tipo visto en el corpus
     * real (Pucamarca, RUC 20100114349 SGS DEL PERU S.A.C.): no trae sección
     * de destinatario, así que se asume igual al remitente —lo habitual acá
     * es que la misma empresa se abastezca a sí misma en otra sede—, y el
     * peso queda fijo en 0 porque el dato simplemente no existe en el PDF.
     *
     * @return array<string, mixed>
     */
    private function extraerCamposBienesFiscalizados(string $texto): array
    {
        $encabezado = $this->capturarBloque('RUC:', 'DATOS DEL INICIO DE TRASLADO', $texto) ?? '';
        $numeroGr = $this->normalizarNumero($this->capturar('/([A-Z]\d{3}\s*-\s*\d+)/u', $encabezado));

        $remitente = $this->capturarBloque(
            'Apellidos, Nombres, Denominación o Razón Social',
            'Tipo y Nro. de documento de identidad',
            $texto,
        );
        $remitenteRuc = $this->capturar('/Tipo y Nro\. de documento de identidad:\s*RUC\s*(\d+)/u', $texto);

        $vehiculos = $this->extraerPlacasBienesFiscalizados($texto);

        return [
            'numero_gr' => $numeroGr,
            'fecha_emision' => $this->capturar('/Fecha de Emisión:\s*(\d{2}\/\d{2}\/\d{4})/u', $texto),
            'fecha_traslado' => $this->capturar('/Fecha y hora de inicio del traslado:\s*(\d{2}\/\d{2}\/\d{4})/u', $texto),
            'origen' => $this->capturarBloque('Dirección del punto de partida', 'Dirección del punto de llegada', $texto),
            'destino' => $this->capturarBloque('Dirección del punto de llegada', 'DATOS DEL REMITENTE', $texto),
            'cliente' => $remitente,
            'cliente_ruc' => $remitenteRuc,
            'destinatario' => $remitente,
            'destinatario_ruc' => $remitenteRuc,
            'subcontratador' => null,
            'subcontratador_ruc' => null,
            'guias_remitente' => [],
            'peso' => '0',
            'unidad_peso' => 'KGM',
            'placa_tracto' => $vehiculos[0] ?? null,
            'placa_carreta' => $vehiculos[1] ?? null,
            ...$this->extraerConductorBienesFiscalizados($texto),
        ];
    }

    /**
     * La tabla «DATOS DEL TRANSPORTE» lista un vehículo por fila (tipo, marca,
     * placa, MTC); acá no hay tracto/carreta como campos separados. Se asume
     * la primera fila como tracto y la segunda como carreta, igual que en el
     * resto de la app.
     *
     * @return list<string>
     */
    private function extraerPlacasBienesFiscalizados(string $texto): array
    {
        $seccion = $this->capturarBloque('DATOS DEL TRANSPORTE', 'DATOS DE(LOS) CONDUCTOR(ES)', $texto) ?? '';

        preg_match_all('/CARRETERA\/TERRESTRE\s+.*?\b([A-Z]{3}\d{3})\b/u', $seccion, $coincidencias);

        return $coincidencias[1];
    }

    /**
     * @return array{conductor_nombre: string|null, conductor_dni: string|null}
     */
    private function extraerConductorBienesFiscalizados(string $texto): array
    {
        $seccion = $this->capturarBloque(
            'DATOS DE(LOS) CONDUCTOR(ES)',
            'Código de verificación',
            $texto,
        ) ?? '';

        if (! preg_match('/DNI\s+(\d+)\s+(.+?)\s+\d+\s+\S+$/u', $seccion, $coincidencias)) {
            return ['conductor_nombre' => null, 'conductor_dni' => null];
        }

        return [
            'conductor_nombre' => trim($coincidencias[2]),
            'conductor_dni' => $coincidencias[1],
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
        $patron = '/'.$this->etiquetaTolerante($etiqueta).'\s*(.*?)\s*-\s*REGISTRO ÚNICO DE CONTRIBUYENTES\s*N°\s*(\d+)/uis';

        if (! preg_match($patron, $texto, $coincidencias)) {
            return [$clave => null, "{$clave}_ruc" => null];
        }

        return [
            $clave => trim($coincidencias[1]),
            "{$clave}_ruc" => $coincidencias[2],
        ];
    }

    /**
     * En una GRE Remitente el cliente real de Paty es quien emite el
     * documento: su razón social encabeza el PDF junto a su RUC, antes de
     * «GUÍA DE REMISIÓN ELECTRÓNICA». No hay sección «Datos del remitente:»
     * como en la GRE Transportista porque ese rol lo cumple el propio emisor.
     *
     * @return array{cliente: string|null, cliente_ruc: string|null}
     */
    private function extraerEmisor(string $texto): array
    {
        if (! preg_match('/^(.*?)\s+RUC\s*N°\s*(\d+)/u', $texto, $coincidencias)) {
            return ['cliente' => null, 'cliente_ruc' => null];
        }

        return [
            'cliente' => trim($coincidencias[1]),
            'cliente_ruc' => $coincidencias[2],
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
     * Texto entre una etiqueta de inicio y la primera etiqueta de corte que
     * aparezca, con los saltos de línea de las direcciones envueltas
     * colapsados a un solo espacio. `$hasta` admite varias etiquetas
     * alternativas para layouts a dos columnas, donde otras etiquetas de la
     * columna vecina pueden caer antes que la de cierre «natural».
     *
     * @param  string|list<string>  $hasta
     */
    private function capturarBloque(string $desde, string|array $hasta, string $texto): ?string
    {
        $alternativas = implode('|', array_map(
            fn (string $etiqueta): string => $this->etiquetaTolerante($etiqueta),
            is_array($hasta) ? $hasta : [$hasta],
        ));

        $patron = '/'.$this->etiquetaTolerante($desde).'\s*(.*?)\s*(?:'.$alternativas.')/us';

        if (! preg_match($patron, $texto, $coincidencias)) {
            return null;
        }

        return trim(preg_replace('/\s+/u', ' ', $coincidencias[1]));
    }

    /**
     * La GRE Transportista pone «:» pegado a la etiqueta; la GRE Remitente a
     * veces lo separa con un espacio («Fecha ... : valor») y a veces no lo
     * trae («Punto de Partida valor», sin «:»). Se admite cualquiera de las
     * tres variantes en vez de mantener un juego de etiquetas por formato.
     */
    private function etiquetaTolerante(string $etiqueta): string
    {
        return preg_quote(rtrim($etiqueta, ':'), '/').'\s*:?';
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
