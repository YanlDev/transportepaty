<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Smalot\PdfParser\Parser;

/**
 * Lee el PDF de una factura electrónica tal como la imprime SUNAT (la
 * «representación impresa» que se descarga de SOL) y devuelve sus datos.
 *
 * A diferencia de la GR, el texto de la factura sale de `getText()` en el
 * mismo orden en que se lee: cada etiqueta queda junto a su valor. Se trabaja
 * sobre el texto con los espacios colapsados, porque los saltos de línea y los
 * tabuladores caen en lugares distintos según el largo de cada campo.
 *
 * Las cifras son las impresas, sin recalcular: la detracción, sobre todo,
 * puede no ser el 4% del total (se calcula sobre el valor referencial cuando
 * es mayor), y la que vale es la de SUNAT.
 *
 * @phpstan-type CamposFactura array{
 *     numero: string,
 *     emisor_ruc: string,
 *     fecha_emision: string,
 *     cliente_razon_social: string|null,
 *     cliente_ruc: string|null,
 *     moneda: string,
 *     observacion: string|null,
 *     valor: float|null,
 *     igv: float|null,
 *     total: float|null,
 *     detraccion: float,
 *     neto: float|null,
 *     fecha_vencimiento: string|null,
 *     gr_transportista: list<string>,
 *     periodo_desde: string|null,
 *     periodo_hasta: string|null,
 * }
 */
class LectorFactura
{
    /** Los meses como los escribe quien redacta la descripción de la factura. */
    private const MESES = [
        'ENERO' => 1,
        'FEBRERO' => 2,
        'MARZO' => 3,
        'ABRIL' => 4,
        'MAYO' => 5,
        'JUNIO' => 6,
        'JULIO' => 7,
        'AGOSTO' => 8,
        'SETIEMBRE' => 9,
        'SEPTIEMBRE' => 9,
        'OCTUBRE' => 10,
        'NOVIEMBRE' => 11,
        'DICIEMBRE' => 12,
    ];

    /**
     * Null si el archivo no es una factura electrónica legible.
     *
     * @return CamposFactura|null
     */
    public function extraerDesdeArchivo(string $ruta): ?array
    {
        return $this->extraerDesdeTexto((new Parser)->parseFile($ruta)->getText());
    }

    /**
     * @return CamposFactura|null
     */
    public function extraerDesdeTexto(string $texto): ?array
    {
        $plano = trim((string) preg_replace('/\s+/u', ' ', $texto));

        if (preg_match('/FACTURA ELECTR[OÓ]NICA RUC\s*:\s*(\d{11})\s+([A-Z0-9]{4}-\d+)/u', $plano, $cabecera) !== 1) {
            return null;
        }

        $fechaEmision = $this->fecha($this->capturar('/Fecha de Emisi[oó]n\s*:\s*(\d{2}\/\d{2}\/\d{4})/u', $plano));

        if ($fechaEmision === null) {
            return null;
        }

        $total = $this->cifra('Importe Total', $plano);
        $detraccion = $this->cifra('Monto detracci[oó]n', $plano) ?? 0.0;
        [$periodoDesde, $periodoHasta] = $this->periodo($plano);

        return [
            'numero' => $cabecera[2],
            'emisor_ruc' => $cabecera[1],
            'fecha_emision' => $fechaEmision,
            'cliente_razon_social' => $this->capturar('/Señor\(es\)\s*:\s*(.+?)\s+RUC\s*:/u', $plano),
            'cliente_ruc' => $this->capturar('/Señor\(es\)\s*:.+?\s+RUC\s*:\s*(\d{11})/u', $plano),
            'moneda' => $this->moneda($plano),
            'observacion' => $this->capturar('/Observaci[oó]n\s*:\s*(.*?)\s+Forma de pago/u', $plano),
            'valor' => $this->cifra('Valor Venta', $plano),
            'igv' => $this->cifra('IGV', $plano),
            'total' => $total,
            'detraccion' => $detraccion,
            'neto' => $this->cifra('Monto neto pendiente de pago', $plano)
                ?? ($total === null ? null : round($total - $detraccion, 2)),
            'fecha_vencimiento' => $this->vencimiento($plano),
            'gr_transportista' => $this->guiasTransportista($plano),
            'periodo_desde' => $periodoDesde,
            'periodo_hasta' => $periodoHasta,
        ];
    }

    /**
     * Las GR-transportista citadas en la cabecera, con el número en el mismo
     * formato que `viajes.numero_gr` (`EG03-00012429`). Sirven las dos
     * etiquetas que usa SUNAT: «GUIA DE REMISION TRANSPORTISTA» y «GRE
     * TRANSPORTISTA - BIENES FISCALIZABLES». Las GR-remitente no cuentan:
     * no son documentos de la empresa.
     *
     * @return list<string>
     */
    private function guiasTransportista(string $plano): array
    {
        preg_match_all('/TRANSPORTISTA(?: - BIENES FISCALIZABLES)?\s*:\s*([A-Z0-9]{4})\s+(\d+)/u', $plano, $coincidencias, PREG_SET_ORDER);

        $guias = array_map(
            fn (array $coincidencia): string => sprintf('%s-%08d', $coincidencia[1], (int) $coincidencia[2]),
            $coincidencias,
        );

        return array_values(array_unique($guias));
    }

    /**
     * El rango de las facturas que cobran un período en vez de GR sueltas:
     * «DESDE EL 25 DE AGOSTO AL 14 DE SETIEMBRE DEL 2026». Sin año en el
     * inicio, es el del final (o el anterior, si el período cruza el año).
     *
     * @return array{0: string|null, 1: string|null}
     */
    private function periodo(string $plano): array
    {
        $meses = implode('|', array_keys(self::MESES));
        $patron = "/DESDE EL (\\d{1,2}) DE ({$meses})(?: DEL? (\\d{4}))? AL (\\d{1,2}) DE ({$meses}) DEL? (\\d{4})/u";

        if (preg_match($patron, $plano, $partes) !== 1) {
            return [null, null];
        }

        $mesDesde = self::MESES[$partes[2]];
        $mesHasta = self::MESES[$partes[5]];
        $anioHasta = (int) $partes[6];
        $anioDesde = $partes[3] !== '' ? (int) $partes[3] : ($mesDesde > $mesHasta ? $anioHasta - 1 : $anioHasta);

        if (! checkdate($mesDesde, (int) $partes[1], $anioDesde) || ! checkdate($mesHasta, (int) $partes[4], $anioHasta)) {
            return [null, null];
        }

        return [
            sprintf('%04d-%02d-%02d', $anioDesde, $mesDesde, (int) $partes[1]),
            sprintf('%04d-%02d-%02d', $anioHasta, $mesHasta, (int) $partes[4]),
        ];
    }

    /**
     * El vencimiento de la última cuota: es cuando la factura termina de
     * vencer. Las facturas al contado no traen cuotas.
     */
    private function vencimiento(string $plano): ?string
    {
        $credito = strstr($plano, 'Información del crédito');

        if ($credito === false) {
            return null;
        }

        preg_match_all('/\b\d{1,2} (\d{2}\/\d{2}\/\d{4}) [\d,]+\.\d{2}/u', $credito, $cuotas);

        $fechas = array_filter(array_map(fn (string $fecha): ?string => $this->fecha($fecha), $cuotas[1]));

        return $fechas === [] ? null : max($fechas);
    }

    private function moneda(string $plano): string
    {
        $moneda = $this->capturar('/Tipo de Moneda\s*:\s*([A-ZÁÉÍÓÚ ]+?)\s+Observaci/u', $plano) ?? 'SOLES';

        return str_contains($moneda, 'DOLAR') || str_contains($moneda, 'DÓLAR') ? 'USD' : 'PEN';
    }

    /** Una cifra impresa junto a su etiqueta, en soles o en dólares. */
    private function cifra(string $etiqueta, string $plano): ?float
    {
        $valor = $this->capturar("/\\b{$etiqueta}\\s*:\\s*(?:S\\/|US\\$|\\$)\\s*([\\d,]+\\.\\d{2})/u", $plano);

        return $valor === null ? null : (float) str_replace(',', '', $valor);
    }

    private function fecha(?string $fecha): ?string
    {
        if ($fecha === null) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('!d/m/Y', $fecha)?->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function capturar(string $patron, string $texto): ?string
    {
        if (preg_match($patron, $texto, $coincidencia) !== 1) {
            return null;
        }

        $valor = trim($coincidencia[1]);

        return $valor === '' ? null : $valor;
    }
}
