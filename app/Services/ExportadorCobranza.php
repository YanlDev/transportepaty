<?php

namespace App\Services;

use App\Enums\EstadoCobranza;
use App\Models\Factura;
use App\Models\Viaje;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
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
 * La cobranza en un .xlsx, con lo que dejan los filtros de `/contabilidad`
 * —los mismos, resueltos por `ResumenCobranza`— para que el archivo se pueda
 * leer al lado de la pantalla sin traducir nada. Cuatro hojas:
 *
 * - «Resumen»: lo que se viene a mirar. Cuánto se facturó, cuánto falta
 *   cobrar en sus dos partes (neto y detracción), cuánto está vencido y desde
 *   cuándo, y lo mismo por cliente.
 * - «Facturas»: una fila por factura, que es como se lleva la cobranza. El
 *   flete descompuesto como lo imprime SUNAT y el cobro de sus dos partes.
 * - «Por GR»: la tabla de la pantalla, una fila por viaje y factura.
 * - «Por asociar»: las facturas que no cubren ninguna GR. No dependen de los
 *   filtros: no caen en ningún mes ni cliente de la tabla.
 *
 * Lo primero que se hace con este archivo es sumar columnas, así que ninguna
 * repite una cifra: en «Por GR» las cifras de la factura van solo en su
 * primera fila y «Valor por GR» reparte el valor entre sus GR. Los totales
 * van con `SUBTOTAL`, que respeta los filtros que se pongan en Excel.
 *
 * Las fechas y los montos van como número y no como texto: escritos como
 * texto, Excel no los suma ni los ordena.
 *
 * @phpstan-import-type FiltrosCobranza from ResumenCobranza
 */
class ExportadorCobranza
{
    /** Las columnas de «Por GR», en el orden de la tabla de la pantalla. */
    private const ENCABEZADOS_POR_GR = [
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
        'Valor flete',
        'IGV',
        'Total flete',
        'Detracción',
        'Neto a pagar',
        'Valor por GR',
        'Moneda',
        'Vence',
        'Días vencida',
        'Fecha pago neto',
        'Cuenta',
        'Fecha detracción',
        'Constancia detracción',
        'Observación',
    ];

    /** Dónde arranca la cobranza en «Por GR» («Estado»), 1-indexada. */
    private const COLUMNA_INICIO_COBRANZA = 13;

    private const ENCABEZADOS_FACTURAS = [
        'N° factura',
        'Fecha emisión',
        'Vence',
        'Días vencida',
        'Estado',
        'Cliente',
        'RUC',
        'GR',
        'Valor flete',
        'IGV',
        'Total flete',
        'Detracción',
        'Neto a pagar',
        'Moneda',
        'Fecha pago neto',
        'Cuenta',
        'Fecha detracción',
        'Constancia detracción',
        'Observación',
    ];

    private const ENCABEZADOS_POR_ASOCIAR = [
        'N° factura',
        'Fecha emisión',
        'Cliente',
        'RUC',
        'Neto a pagar',
        'Moneda',
        'GR que cita',
        'Período desde',
        'Período hasta',
    ];

    /**
     * El semáforo de la pantalla, en el relleno de la celda del estado: rojo
     * lo que hay que facturar, ámbar lo que se espera, celeste lo que solo
     * espera la detracción, verde lo cerrado y gris lo que no se cobra.
     */
    private const COLOR_ESTADO = [
        'sin_facturar' => 'FEE2E2',
        'facturado' => 'FEF3C7',
        'falta_detraccion' => 'E0F2FE',
        'pagado' => 'DCFCE7',
        'no_facturable' => 'F4F4F5',
    ];

    private const COLOR_ENCABEZADO = 'EFF2F7';

    private const COLOR_VENCIDA = 'B91C1C';

    private const FORMATO_DINERO = '#,##0.00';

    /** Los tramos de antigüedad de la deuda, en días pasados del vencimiento. */
    private const TRAMOS = [
        'Por vencer' => [null, 0],
        '1 a 30 días' => [1, 30],
        '31 a 60 días' => [31, 60],
        'Más de 60 días' => [61, null],
    ];

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
        $facturas = $this->facturasDelFiltro($filtros);

        $resumen = $libro->getActiveSheet();
        $resumen->setTitle('Resumen');
        $this->escribirResumen($resumen, $filtros, $facturas);

        $this->escribirFacturas($libro->createSheet()->setTitle('Facturas'), $facturas);
        $this->escribirPorGr($libro->createSheet()->setTitle('Por GR'), $filtros);
        $this->escribirPorAsociar($libro->createSheet()->setTitle('Por asociar'));

        // Se abre en el resumen: es lo que se viene a mirar.
        $libro->setActiveSheetIndex(0);

        (new Xlsx($libro))->save($ruta);

        // PhpSpreadsheet deja el libro entero en memoria hasta que lo recoge
        // el GC; en una descarga bajo demanda conviene soltarlo acá.
        $libro->disconnectWorksheets();
    }

    /**
     * Las facturas de los viajes que dejan los filtros: el mismo recorte que
     * los totales de la pantalla. Una factura de varias GR aparece una vez.
     *
     * @param  FiltrosCobranza  $filtros
     * @return Collection<int, Factura>
     */
    private function facturasDelFiltro(array $filtros): Collection
    {
        return Factura::query()
            ->whereHas('viajes', fn ($query) => $query->whereIn(
                'viajes.id',
                $this->cobranza->consulta($filtros)->reorder()->select('viajes.id'),
            ))
            ->with([
                'cuentaBancaria:id,alias',
                'viajes' => fn ($query) => $query->orderBy('numero_gr'),
                'viajes.clienteDelPadron:id,alias',
            ])
            ->orderBy('fecha_emision')
            ->orderBy('numero')
            ->get();
    }

    // ─── Resumen ────────────────────────────────────────────────────────────

    /**
     * @param  FiltrosCobranza  $filtros
     * @param  Collection<int, Factura>  $facturas
     */
    private function escribirResumen(Worksheet $hoja, array $filtros, Collection $facturas): void
    {
        $hoja->setCellValue('A1', 'Reporte de cobranza');
        $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $hoja->setCellValue('A2', 'Generado el '.RelojOperativo::ahora()->format('d/m/Y H:i').' · '.$this->describirFiltros($filtros));
        $hoja->getStyle('A2')->getFont()->setItalic(true)->getColor()->setRGB('6B7280');

        $fila = 4;

        foreach ($facturas->groupBy(fn (Factura $factura): string => $factura->moneda->value) as $moneda => $delaMoneda) {
            $fila = $this->escribirTotalesDeMoneda($hoja, $fila, (string) $moneda, $delaMoneda);
            $fila = $this->escribirAntiguedad($hoja, $fila, $delaMoneda);
            $fila = $this->escribirPorCliente($hoja, $fila, $delaMoneda);
        }

        if ($facturas->isEmpty()) {
            $hoja->setCellValue([1, $fila], 'No hay facturas bajo estos filtros.');
            $fila += 2;
        }

        $this->escribirPorFacturar($hoja, $fila, $filtros);

        $hoja->getColumnDimension('A')->setWidth(42);

        foreach (range(2, 8) as $columna) {
            $hoja->getColumnDimensionByColumn($columna)->setWidth(18);
        }
    }

    /**
     * Las cifras grandes de una moneda: sumar soles con dólares da un número
     * que no significa nada, así que cada moneda va en su bloque.
     *
     * @param  Collection<int, Factura>  $facturas
     */
    private function escribirTotalesDeMoneda(Worksheet $hoja, int $fila, string $moneda, Collection $facturas): int
    {
        $this->titulo($hoja, $fila, "Totales en {$moneda}");
        $fila++;

        $vencidas = $facturas->filter(fn (Factura $factura): bool => ($factura->diasVencida() ?? 0) > 0);

        $cifras = [
            'Facturas' => [$facturas->count(), '0'],
            'Total facturado (con IGV)' => [$this->suma($facturas, 'total'), self::FORMATO_DINERO],
            'Neto por cobrar' => [$facturas->sum(fn (Factura $factura): float => $this->netoPendiente($factura)), self::FORMATO_DINERO],
            'Detracción por cobrar' => [$facturas->sum(fn (Factura $factura): float => $this->detraccionPendiente($factura)), self::FORMATO_DINERO],
            'Cobrado (neto + detracción)' => [$facturas->sum(fn (Factura $factura): float => $this->cobrado($factura)), self::FORMATO_DINERO],
            'Vencido sin cobrar' => [$vencidas->sum(fn (Factura $factura): float => $this->pendiente($factura)), self::FORMATO_DINERO],
            'Facturas vencidas' => [$vencidas->count(), '0'],
            'Facturas sin monto cargado' => [$facturas->whereNull('total')->count(), '0'],
        ];

        foreach ($cifras as $etiqueta => [$valor, $formato]) {
            $hoja->setCellValue([1, $fila], $etiqueta);
            $hoja->setCellValue([2, $fila], round((float) $valor, 2));
            $hoja->getStyle([2, $fila])->getNumberFormat()->setFormatCode($formato);
            $fila++;
        }

        $hoja->getStyle([2, $fila - 3])->getFont()->setBold(true)->getColor()->setRGB(self::COLOR_VENCIDA);

        return $fila + 1;
    }

    /**
     * Cuánto falta cobrar según cuánto hace que venció. Es la pregunta de la
     * semana: qué hay que llamar a cobrar primero.
     *
     * @param  Collection<int, Factura>  $facturas
     */
    private function escribirAntiguedad(Worksheet $hoja, int $fila, Collection $facturas): int
    {
        $this->titulo($hoja, $fila, 'Antigüedad de lo que falta cobrar');
        $fila++;
        $this->encabezado($hoja, $fila, ['Tramo', 'Facturas', 'Neto', 'Detracción', 'Total pendiente']);
        $fila++;

        $pendientes = $facturas->filter(fn (Factura $factura): bool => $this->pendiente($factura) > 0);

        foreach (self::TRAMOS as $tramo => [$desde, $hasta]) {
            $delTramo = $pendientes->filter(function (Factura $factura) use ($desde, $hasta): bool {
                $dias = $factura->diasVencida() ?? 0;

                return ($desde === null || $dias >= $desde) && ($hasta === null || $dias <= $hasta);
            });

            $hoja->fromArray([
                $tramo,
                $delTramo->count(),
                round($delTramo->sum(fn (Factura $factura): float => $this->netoPendiente($factura)), 2),
                round($delTramo->sum(fn (Factura $factura): float => $this->detraccionPendiente($factura)), 2),
                round($delTramo->sum(fn (Factura $factura): float => $this->pendiente($factura)), 2),
            ], null, 'A'.$fila, true);

            if ($desde !== null && $delTramo->isNotEmpty()) {
                $hoja->getStyle([1, $fila, 5, $fila])->getFont()->getColor()->setRGB(self::COLOR_VENCIDA);
            }

            $fila++;
        }

        $this->formatear($hoja, 3, $fila - 4, $fila - 1, self::FORMATO_DINERO, hastaColumna: 5);

        return $fila + 1;
    }

    /**
     * @param  Collection<int, Factura>  $facturas
     */
    private function escribirPorCliente(Worksheet $hoja, int $fila, Collection $facturas): int
    {
        $this->titulo($hoja, $fila, 'Por cliente');
        $fila++;
        $this->encabezado($hoja, $fila, ['Cliente', 'Facturas', 'Total facturado', 'Neto por cobrar', 'Detracción por cobrar', 'Cobrado', 'Vencido sin cobrar']);
        $fila++;
        $desde = $fila;

        // Por RUC y no por nombre: la misma empresa llega como «Crisar
        // Logistica» desde el padrón y como «CRISAR LOGISTICA S.A.C.» desde
        // el PDF de la factura, y por nombre quedaría partida en dos.
        $porCliente = $facturas
            ->groupBy(fn (Factura $factura): string => $this->rucDe($factura) ?? $this->clienteDe($factura))
            ->sortByDesc(fn (Collection $delCliente): float => $delCliente->sum(fn (Factura $factura): float => $this->pendiente($factura)));

        foreach ($porCliente as $delCliente) {
            $hoja->fromArray([
                $this->clienteDe($delCliente->first()),
                $delCliente->count(),
                round($this->suma($delCliente, 'total'), 2),
                round($delCliente->sum(fn (Factura $factura): float => $this->netoPendiente($factura)), 2),
                round($delCliente->sum(fn (Factura $factura): float => $this->detraccionPendiente($factura)), 2),
                round($delCliente->sum(fn (Factura $factura): float => $this->cobrado($factura)), 2),
                round($delCliente
                    ->filter(fn (Factura $factura): bool => ($factura->diasVencida() ?? 0) > 0)
                    ->sum(fn (Factura $factura): float => $this->pendiente($factura)), 2),
            ], null, 'A'.$fila, true);
            $fila++;
        }

        $this->filaTotales($hoja, $fila, $desde, [2, 3, 4, 5, 6, 7], etiqueta: 1);
        $this->formatear($hoja, 3, $desde, $fila, self::FORMATO_DINERO, hastaColumna: 7);

        return $fila + 2;
    }

    /**
     * Las GR que faltan facturar, por mes: no tienen monto todavía, así que se
     * cuentan, no se suman.
     *
     * @param  FiltrosCobranza  $filtros
     */
    private function escribirPorFacturar(Worksheet $hoja, int $fila, array $filtros): void
    {
        $this->titulo($hoja, $fila, 'GR por facturar');
        $fila++;

        $porMes = $this->cobranza->consulta($filtros)
            ->whereDoesntHave('facturas')
            ->whereNull('no_facturable_at')
            ->reorder()
            ->pluck('fecha_traslado')
            ->groupBy(fn (CarbonInterface $fecha): string => $fecha->format('Y-m'))
            ->sortKeysDesc();

        if ($porMes->isEmpty()) {
            $hoja->setCellValue([1, $fila], 'No queda nada por facturar.');

            return;
        }

        $this->encabezado($hoja, $fila, ['Mes', 'GR']);
        $fila++;

        foreach ($porMes as $mes => $fechas) {
            $hoja->setCellValue([1, $fila], ResumenCobranza::MESES[(int) substr((string) $mes, 5, 2)].' '.substr((string) $mes, 0, 4));
            $hoja->setCellValue([2, $fila], $fechas->count());
            $fila++;
        }
    }

    // ─── Facturas ───────────────────────────────────────────────────────────

    /**
     * @param  Collection<int, Factura>  $facturas
     */
    private function escribirFacturas(Worksheet $hoja, Collection $facturas): void
    {
        $this->encabezado($hoja, 1, self::ENCABEZADOS_FACTURAS);
        $fila = 1;

        foreach ($facturas as $factura) {
            $fila++;
            $estado = $factura->estado();
            $dias = $factura->diasVencida();

            $this->escribirFila($hoja, $fila, [
                $factura->numero,
                $this->fecha($factura->fecha_emision),
                $this->fecha($factura->fechaVencimiento()),
                $dias,
                $estado->label(),
                $this->clienteDe($factura),
                $this->rucDe($factura),
                $factura->viajes->pluck('numero_gr')->join(', '),
                $this->cifra($factura->monto),
                $this->cifra($factura->igv),
                $this->cifra($factura->total),
                $this->cifra($factura->detraccion),
                $this->cifra($factura->neto),
                $factura->moneda->value,
                $factura->fecha_pago === null ? null : $this->fecha($factura->fecha_pago),
                $factura->cuentaBancaria?->alias,
                $factura->fecha_detraccion === null ? null : $this->fecha($factura->fecha_detraccion),
                $factura->constancia_detraccion,
                $factura->observacion,
            ]);

            $this->pintarEstado($hoja, 5, $fila, $estado->value);

            if (($dias ?? 0) > 0) {
                $hoja->getStyle([3, $fila, 4, $fila])->getFont()->setBold(true)->getColor()->setRGB(self::COLOR_VENCIDA);
            }
        }

        $ultima = $fila;

        if ($ultima > 1) {
            $this->filaTotales($hoja, $ultima + 1, 2, range(9, 13), etiqueta: 1);
        }

        $this->darFormatoTabla($hoja, count(self::ENCABEZADOS_FACTURAS), max($ultima, 1), fechas: [2, 3, 15, 17], dinero: range(9, 13), totales: $ultima > 1);
    }

    // ─── Por GR ─────────────────────────────────────────────────────────────

    /**
     * @param  FiltrosCobranza  $filtros
     */
    private function escribirPorGr(Worksheet $hoja, array $filtros): void
    {
        $this->encabezado($hoja, 1, self::ENCABEZADOS_POR_GR);
        $fila = 1;

        /** @var array<int, true> $facturasEscritas */
        $facturasEscritas = [];

        $viajes = $this->cobranza->consulta($filtros)
            // Solo lo que lee `celdasPorGr()`: las placas y el conductor salen
            // de columnas del propio viaje, no de las relaciones.
            ->with([
                'clienteDelPadron:id,alias',
                'facturas' => fn ($query) => $query->orderBy('fecha_emision')->orderBy('facturas.id'),
                'facturas.cuentaBancaria:id,alias',
                'facturas.viajes:viajes.id',
            ])
            ->orderByDesc('fecha_traslado')
            ->orderByDesc('numero_gr')
            ->lazy();

        foreach ($viajes as $viaje) {
            // Sin facturas, una sola fila con la cobranza vacía.
            $facturas = $viaje->facturas->isEmpty() ? [null] : $viaje->facturas->all();

            foreach ($facturas as $factura) {
                $fila++;

                $primeraVez = $factura !== null && ! isset($facturasEscritas[$factura->id]);

                if ($factura !== null) {
                    $facturasEscritas[$factura->id] = true;
                }

                $this->escribirFila($hoja, $fila, $this->celdasPorGr($viaje, $factura, $primeraVez));
                $this->pintarEstado($hoja, 13, $fila, $viaje->estadoCobranza()->value);

                if (($factura?->diasVencida() ?? 0) > 0) {
                    $hoja->getStyle([23, $fila, 24, $fila])->getFont()->setBold(true)->getColor()->setRGB(self::COLOR_VENCIDA);
                }
            }
        }

        $ultima = $fila;

        if ($ultima > 1) {
            $this->filaTotales($hoja, $ultima + 1, 2, range(16, 21), etiqueta: 1);
        }

        // La misma línea que separa operación de cobranza en la tabla.
        $hoja->getStyle([self::COLUMNA_INICIO_COBRANZA, 1, self::COLUMNA_INICIO_COBRANZA, max($ultima, 1)])
            ->getBorders()->getLeft()->setBorderStyle(Border::BORDER_MEDIUM);

        $this->darFormatoTabla($hoja, count(self::ENCABEZADOS_POR_GR), max($ultima, 1), fechas: [1, 15, 23, 25, 27], dinero: range(16, 21), totales: $ultima > 1);
        if ($ultima > 1) {
            $this->formatear($hoja, 12, 2, $ultima, '#,##0.00');

            // La columna de GR remitente lleva varias por celda.
            $hoja->getStyle([3, 2, 3, $ultima])
                ->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        }
    }

    /**
     * Los valores de una fila de «Por GR», en el orden de sus encabezados.
     *
     * @return array<int, string|float|int|null>
     */
    private function celdasPorGr(Viaje $viaje, ?Factura $factura, bool $primeraVez): array
    {
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
            $primeraVez ? $this->cifra($factura?->monto) : null,
            $primeraVez ? $this->cifra($factura?->igv) : null,
            $primeraVez ? $this->cifra($factura?->total) : null,
            $primeraVez ? $this->cifra($factura?->detraccion) : null,
            $primeraVez ? $this->cifra($factura?->neto) : null,
            $factura === null ? null : $this->montoPorGr($factura, $viaje),
            $factura?->moneda->value,
            $factura === null ? null : $this->fecha($factura->fechaVencimiento()),
            $factura?->diasVencida(),
            $factura?->fecha_pago === null ? null : $this->fecha($factura->fecha_pago),
            $factura?->cuentaBancaria?->alias,
            $factura?->fecha_detraccion === null ? null : $this->fecha($factura->fecha_detraccion),
            $factura?->constancia_detraccion,
            $factura === null ? $viaje->motivo_no_facturable : $factura->observacion,
        ];
    }

    // ─── Por asociar ────────────────────────────────────────────────────────

    private function escribirPorAsociar(Worksheet $hoja): void
    {
        $this->encabezado($hoja, 1, self::ENCABEZADOS_POR_ASOCIAR);
        $fila = 1;

        $facturas = Factura::query()
            ->whereDoesntHave('viajes')
            ->orderBy('fecha_emision')
            ->get();

        foreach ($facturas as $factura) {
            $fila++;

            $this->escribirFila($hoja, $fila, [
                $factura->numero,
                $this->fecha($factura->fecha_emision),
                $factura->cliente_razon_social,
                $factura->cliente_ruc,
                $this->cifra($factura->neto),
                $factura->moneda->value,
                implode(', ', $factura->gr_citadas ?? []),
                $factura->periodo_desde === null ? null : $this->fecha($factura->periodo_desde),
                $factura->periodo_hasta === null ? null : $this->fecha($factura->periodo_hasta),
            ]);
        }

        $this->darFormatoTabla($hoja, count(self::ENCABEZADOS_POR_ASOCIAR), max($fila, 1), fechas: [2, 8, 9], dinero: [5], totales: false);
    }

    // ─── Cifras de una factura ──────────────────────────────────────────────

    private function netoPendiente(Factura $factura): float
    {
        return $factura->fecha_pago === null ? (float) $factura->neto : 0.0;
    }

    private function detraccionPendiente(Factura $factura): float
    {
        return $factura->faltaDetraccion() ? (float) $factura->detraccion : 0.0;
    }

    private function pendiente(Factura $factura): float
    {
        return $this->netoPendiente($factura) + $this->detraccionPendiente($factura);
    }

    private function cobrado(Factura $factura): float
    {
        return ($factura->fecha_pago === null ? 0.0 : (float) $factura->neto)
            + ($factura->fecha_detraccion === null ? 0.0 : (float) $factura->detraccion);
    }

    /**
     * @param  Collection<int, Factura>  $facturas
     */
    private function suma(Collection $facturas, string $campo): float
    {
        return (float) $facturas->sum(fn (Factura $factura): float => (float) $factura->{$campo});
    }

    /**
     * A quién se facturó: el nombre del padrón (el mismo que usa la tabla),
     * y si la factura no tiene GR, la razón social impresa en el PDF.
     */
    private function clienteDe(Factura $factura): string
    {
        return $factura->viajes->first()?->nombreCliente()
            ?? $factura->cliente_razon_social
            ?? 'Sin cliente';
    }

    private function rucDe(Factura $factura): ?string
    {
        return $factura->cliente_ruc ?? $factura->viajes->first()?->cliente_ruc;
    }

    /**
     * La parte del valor de la factura que le toca a esta GR, en partes
     * iguales. Se reparte en céntimos y lo que sobra de la división va a las
     * primeras GR (por id), para que la columna sume exactamente el valor de
     * la factura: 100.00 entre 3 da 33.34 + 33.33 + 33.33.
     */
    private function montoPorGr(Factura $factura, Viaje $viaje): ?float
    {
        if ($factura->monto === null) {
            return null;
        }

        $ids = $factura->viajes->pluck('id')->sort()->values();
        $cantidad = max($ids->count(), 1);
        $centimos = (int) round((float) $factura->monto * 100);
        $posicion = $ids->search($viaje->id);
        $extra = $posicion !== false && $posicion < $centimos % $cantidad ? 1 : 0;

        return (intdiv($centimos, $cantidad) + $extra) / 100;
    }

    // ─── Datos del viaje ────────────────────────────────────────────────────

    /**
     * El peso en toneladas, como lo muestra la tabla. La GR lo trae en KGM o
     * en TNE y se guarda tal cual vino; sin convertir, la columna mezclaría
     * 23.755 con 23755 y su suma no significaría nada. Es la contraparte de
     * `formatearPeso()` en `resources/js/lib/format.ts`.
     */
    private function toneladas(Viaje $viaje): float
    {
        $peso = (float) $viaje->peso;

        return $viaje->unidad_peso === 'KGM' ? $peso / 1000 : $peso;
    }

    /** Las GR del remitente separadas por salto de línea, como en pantalla. */
    private function guiasRemitente(Viaje $viaje): ?string
    {
        $guias = $viaje->guias_remitente;

        if ($guias === null || $guias === []) {
            return null;
        }

        return implode("\n", array_column($guias, 'numero'));
    }

    // ─── Formato ────────────────────────────────────────────────────────────

    /**
     * Los filtros con que se generó, en palabras, para que el archivo diga
     * qué recorte es aunque se lea días después.
     *
     * @param  FiltrosCobranza  $filtros
     */
    private function describirFiltros(array $filtros): string
    {
        $partes = array_filter([
            $filtros['cliente'] ? "cliente {$filtros['cliente']}" : null,
            $filtros['estado'] ? 'estado «'.(EstadoCobranza::tryFrom($filtros['estado'])?->label() ?? $filtros['estado']).'»' : null,
            $filtros['mes'] ? 'mes '.ResumenCobranza::MESES[(int) substr($filtros['mes'], 5, 2)].' '.substr($filtros['mes'], 0, 4) : null,
            $filtros['desde'] ? 'desde '.Carbon::parse($filtros['desde'])->format('d/m/Y') : null,
            $filtros['hasta'] ? 'hasta '.Carbon::parse($filtros['hasta'])->format('d/m/Y') : null,
            $filtros['buscar'] ? "búsqueda «{$filtros['buscar']}»" : null,
        ]);

        return $partes === [] ? 'sin filtros' : 'filtros: '.implode(', ', $partes);
    }

    private function titulo(Worksheet $hoja, int $fila, string $texto): void
    {
        $hoja->setCellValue([1, $fila], $texto);
        $hoja->getStyle([1, $fila])->getFont()->setBold(true)->setSize(12);
    }

    /**
     * @param  array<int, string>  $encabezados
     */
    private function encabezado(Worksheet $hoja, int $fila, array $encabezados): void
    {
        foreach ($encabezados as $indice => $encabezado) {
            $hoja->setCellValue([$indice + 1, $fila], $encabezado);
        }

        $estilo = $hoja->getStyle([1, $fila, count($encabezados), $fila]);
        $estilo->getFont()->setBold(true);
        $estilo->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::COLOR_ENCABEZADO);
    }

    /**
     * @param  array<int, string|float|int|null>  $valores
     */
    private function escribirFila(Worksheet $hoja, int $fila, array $valores): void
    {
        foreach ($valores as $columna => $valor) {
            if ($valor === null) {
                continue;
            }

            $hoja->setCellValue([$columna + 1, $fila], $valor);
        }
    }

    private function pintarEstado(Worksheet $hoja, int $columna, int $fila, string $estado): void
    {
        $color = self::COLOR_ESTADO[$estado] ?? null;

        if ($color !== null) {
            $hoja->getStyle([$columna, $fila])->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($color);
        }
    }

    /**
     * Una fila de totales con `SUBTOTAL(109, …)`: suma solo lo visible, así
     * que sigue siendo cierta después de filtrar en Excel.
     *
     * @param  non-empty-array<int, int>  $columnas
     */
    private function filaTotales(Worksheet $hoja, int $fila, int $desde, array $columnas, int $etiqueta): void
    {
        $hoja->setCellValue([$etiqueta, $fila], 'Total');

        foreach ($columnas as $columna) {
            $letra = Coordinate::stringFromColumnIndex($columna);
            $hoja->setCellValue([$columna, $fila], "=SUBTOTAL(109,{$letra}{$desde}:{$letra}".($fila - 1).')');
        }

        $estilo = $hoja->getStyle([1, $fila, max($columnas), $fila]);
        $estilo->getFont()->setBold(true);
        $estilo->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
    }

    /**
     * Encabezado fijo, filtro automático, anchos automáticos y formato de
     * fechas y montos por columna.
     *
     * @param  array<int, int>  $fechas
     * @param  array<int, int>  $dinero
     */
    private function darFormatoTabla(Worksheet $hoja, int $columnas, int $ultimaFila, array $fechas, array $dinero, bool $totales): void
    {
        $hoja->freezePane('A2');
        $hoja->setAutoFilter('A1:'.Coordinate::stringFromColumnIndex($columnas).$ultimaFila);

        foreach (range(1, $columnas) as $columna) {
            $hoja->getColumnDimensionByColumn($columna)->setAutoSize(true);
        }

        if ($ultimaFila < 2) {
            return;
        }

        $hastaDinero = $totales ? $ultimaFila + 1 : $ultimaFila;

        foreach ($fechas as $columna) {
            $this->formatear($hoja, $columna, 2, $ultimaFila, NumberFormat::FORMAT_DATE_DDMMYYYY);
        }

        foreach ($dinero as $columna) {
            $this->formatear($hoja, $columna, 2, $hastaDinero, self::FORMATO_DINERO);
        }
    }

    private function formatear(Worksheet $hoja, int $columna, int $desde, int $hasta, string $formato, ?int $hastaColumna = null): void
    {
        $hoja->getStyle([$columna, $desde, $hastaColumna ?? $columna, $hasta])
            ->getNumberFormat()->setFormatCode($formato);
    }

    private function cifra(?string $valor): ?float
    {
        return $valor === null ? null : (float) $valor;
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
}
