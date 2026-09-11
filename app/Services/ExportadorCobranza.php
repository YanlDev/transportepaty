<?php

namespace App\Services;

use App\Models\Viaje;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * La tabla de cobranza tal como se ve en pantalla, pero en un .xlsx.
 *
 * Exporta exactamente lo que dejan los filtros —los mismos que usa
 * `/contabilidad`, resueltos por `ResumenCobranza`— y en el mismo orden, para
 * que el archivo se pueda leer al lado de la pantalla sin traducir nada. Una
 * fila por viaje, igual que la tabla: cuando una factura cubre varios viajes,
 * su número y su monto se repiten, porque lo que se exporta es el viaje y no
 * la factura.
 *
 * Escribe sin paginar. La consulta se recorre con `lazy()` en lugar de
 * `get()` para que el pico de memoria no dependa de cuántos viajes haya
 * debajo del filtro.
 *
 * @phpstan-import-type FiltrosCobranza from ResumenCobranza
 */
class ExportadorCobranza
{
    /**
     * Las columnas, en el orden de la tabla. La línea entre la operación y la
     * plata se marca con un borde en `COLUMNA_INICIO_COBRANZA`, igual que en
     * pantalla: la tabla es ancha y sin esa marca no se ve dónde empieza lo
     * que se factura.
     *
     * @var array<int, string>
     */
    private const ENCABEZADOS = [
        'Fecha',
        'N° GR',
        'GR remitente',
        'Tracto',
        'Carreta',
        'Conductor',
        'Cliente',
        'Destinatario',
        'Origen',
        'Destino',
        'Tipo de carga',
        'Peso (TNE)',
        'Estado',
        'N° factura',
        'Fecha emisión',
        'Monto',
        'Moneda',
        'Fecha pago',
        'Días vencida',
        'Cuenta',
        'Observación',
    ];

    /**
     * La columna donde arranca la cobranza («Estado»), 1-indexada.
     */
    private const COLUMNA_INICIO_COBRANZA = 13;

    private const FILA_ENCABEZADO = 1;

    public function __construct(private readonly ResumenCobranza $cobranza) {}

    /**
     * El nombre del archivo lleva la fecha de emisión para que dos descargas
     * del mismo día no se pisen en la carpeta de descargas.
     */
    public function nombreArchivo(): string
    {
        return 'cobranza-'.Carbon::now()->format('Y-m-d-Hi').'.xlsx';
    }

    /**
     * @param  FiltrosCobranza  $filtros
     */
    public function escribir(array $filtros, string $ruta): void
    {
        $libro = new Spreadsheet;
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Cobranza');

        $this->escribirEncabezado($hoja);
        $ultimaFila = $this->escribirFilas($hoja, $filtros);
        $this->darFormato($hoja, $ultimaFila);

        (new Xlsx($libro))->save($ruta);

        // PhpSpreadsheet deja el libro entero en memoria hasta que lo recoge
        // el GC; en una descarga bajo demanda conviene soltarlo acá.
        $libro->disconnectWorksheets();
    }

    private function escribirEncabezado(Worksheet $hoja): void
    {
        foreach (self::ENCABEZADOS as $indice => $encabezado) {
            $hoja->setCellValue([$indice + 1, self::FILA_ENCABEZADO], $encabezado);
        }
    }

    /**
     * @param  FiltrosCobranza  $filtros
     * @return int La última fila escrita, o la del encabezado si no hubo datos.
     */
    private function escribirFilas(Worksheet $hoja, array $filtros): int
    {
        $fila = self::FILA_ENCABEZADO;

        $viajes = $this->cobranza->consulta($filtros)
            // Solo lo que lee `celdas()`: las placas y el conductor salen de
            // columnas del propio viaje, no de las relaciones.
            ->with([
                'clienteDelPadron:id,alias',
                'factura.cuentaBancaria:id,alias',
            ])
            ->orderByDesc('fecha_traslado')
            ->orderByDesc('numero_gr')
            ->lazy();

        foreach ($viajes as $viaje) {
            $fila++;

            foreach ($this->celdas($viaje) as $columna => $valor) {
                if ($valor === null) {
                    continue;
                }

                $hoja->setCellValue([$columna + 1, $fila], $valor);
            }
        }

        return $fila;
    }

    /**
     * Los valores de una fila, en el orden de `ENCABEZADOS`.
     *
     * Las fechas y los montos van como número y no como texto: escritos como
     * texto, Excel no los suma ni los ordena, que es lo primero que se hace
     * con este archivo. El formato se aplica por columna en `darFormato()`.
     *
     * @return array<int, string|float|int|null>
     */
    private function celdas(Viaje $viaje): array
    {
        $factura = $viaje->factura;

        return [
            $this->fecha($viaje->fecha_traslado),
            $viaje->numero_gr,
            $this->guiasRemitente($viaje),
            $viaje->placa_tracto,
            $viaje->placa_carreta,
            $viaje->conductor_nombre,
            $viaje->nombreCliente(),
            $viaje->destinatario,
            $viaje->ciudadOrigen(),
            $viaje->ciudadDestino(),
            $viaje->tipo_carga->label(),
            $this->toneladas($viaje),
            $viaje->estadoCobranza()->label(),
            $factura?->numero,
            $factura === null ? null : $this->fecha($factura->fecha_emision),
            $factura?->monto === null ? null : (float) $factura->monto,
            $factura?->moneda->value,
            $factura?->fecha_pago === null ? null : $this->fecha($factura->fecha_pago),
            $factura?->diasVencida(),
            $factura?->cuentaBancaria?->alias,
            $factura?->observacion,
        ];
    }

    /**
     * El peso en toneladas, como lo muestra la tabla.
     *
     * La GR lo trae en KGM o en TNE —los dos códigos SUNAT que se usan acá— y
     * se guarda tal cual vino; la conversión es de presentación. Sin esto, la
     * columna mezclaría 23.755 con 23755 según cómo vino cada guía, y la suma
     * de la columna no significaría nada. Es la contraparte de
     * `formatearPeso()` en `resources/js/lib/format.ts`.
     */
    private function toneladas(Viaje $viaje): float
    {
        $peso = (float) $viaje->peso;

        return $viaje->unidad_peso === 'KGM' ? $peso / 1000 : $peso;
    }

    /**
     * Las GR del remitente separadas por salto de línea, que es como se
     * apilan en la pantalla. Un viaje puede cubrir varias.
     */
    private function guiasRemitente(Viaje $viaje): ?string
    {
        $guias = $viaje->guias_remitente;

        if ($guias === null || $guias === []) {
            return null;
        }

        return implode("\n", array_column($guias, 'numero'));
    }

    /**
     * El serial de fecha que entiende Excel: días enteros desde el 30-12-1899.
     *
     * Se cuenta sobre la fecha pelada y no con `Date::PHPToExcel()` porque esa
     * conversión pasa por la zona horaria del proceso, y con un huso al oeste
     * de UTC —el de Lima— devuelve el día anterior.
     */
    private function fecha(CarbonInterface $fecha): float
    {
        return (float) Carbon::parse('1899-12-30')
            ->diffInDays($fecha->copy()->startOfDay(), absolute: true);
    }

    private function darFormato(Worksheet $hoja, int $ultimaFila): void
    {
        $ultimaColumna = count(self::ENCABEZADOS);

        $hoja->getStyle([1, self::FILA_ENCABEZADO, $ultimaColumna, self::FILA_ENCABEZADO])
            ->getFont()->setBold(true);

        $hoja->getStyle([1, self::FILA_ENCABEZADO, $ultimaColumna, self::FILA_ENCABEZADO])
            ->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('EFF2F7');

        // La misma línea que separa operación de cobranza en la tabla.
        $hoja->getStyle([self::COLUMNA_INICIO_COBRANZA, self::FILA_ENCABEZADO, self::COLUMNA_INICIO_COBRANZA, max($ultimaFila, self::FILA_ENCABEZADO)])
            ->getBorders()->getLeft()->setBorderStyle(Border::BORDER_MEDIUM);

        $hoja->freezePane('A2');

        $ultimaLetra = Coordinate::stringFromColumnIndex($ultimaColumna);
        $filaFinal = max($ultimaFila, self::FILA_ENCABEZADO);
        $hoja->setAutoFilter('A'.self::FILA_ENCABEZADO.':'.$ultimaLetra.$filaFinal);

        foreach (range(1, $ultimaColumna) as $columna) {
            $hoja->getColumnDimensionByColumn($columna)->setAutoSize(true);
        }

        if ($ultimaFila <= self::FILA_ENCABEZADO) {
            return;
        }

        $primeraFila = self::FILA_ENCABEZADO + 1;

        $this->formatear($hoja, 1, $primeraFila, $ultimaFila, NumberFormat::FORMAT_DATE_DDMMYYYY);
        $this->formatear($hoja, 12, $primeraFila, $ultimaFila, '#,##0.00');
        $this->formatear($hoja, 15, $primeraFila, $ultimaFila, NumberFormat::FORMAT_DATE_DDMMYYYY);
        $this->formatear($hoja, 16, $primeraFila, $ultimaFila, '#,##0.00');
        $this->formatear($hoja, 18, $primeraFila, $ultimaFila, NumberFormat::FORMAT_DATE_DDMMYYYY);

        // La columna de GR remitente lleva varias por celda.
        $hoja->getStyle([3, $primeraFila, 3, $ultimaFila])
            ->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
    }

    private function formatear(Worksheet $hoja, int $columna, int $desde, int $hasta, string $formato): void
    {
        $hoja->getStyle([$columna, $desde, $columna, $hasta])
            ->getNumberFormat()->setFormatCode($formato);
    }
}
