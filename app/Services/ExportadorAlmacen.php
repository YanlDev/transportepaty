<?php

namespace App\Services;

use App\Models\Articulo;
use App\Models\MovimientoAlmacen;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * El almacén en Excel.
 *
 * - `stock()`: una fila por artículo con su saldo y, para quien ve costos, su
 *   valor. Es la foto del inventario valorizado.
 * - `kardex()`: el registro de inventario permanente valorizado en el orden
 *   del formato 13.1 de SUNAT: saldo anterior, cada movimiento con sus
 *   entradas, salidas y saldo en cantidad, costo unitario y total.
 *
 * Cantidades y montos van como número, no como texto, para que Excel sume.
 */
class ExportadorAlmacen
{
    private const COLOR_ENCABEZADO = 'FF1F2937';

    private const FORMATO_CANTIDAD = '#,##0.###';

    private const FORMATO_SOLES = '#,##0.00';

    private const FORMATO_UNITARIO = '#,##0.0000';

    /**
     * @param  Collection<int, Articulo>  $articulos
     */
    public function stock(Collection $articulos, bool $conCostos, string $ruta): void
    {
        $libro = new Spreadsheet;
        $hoja = $libro->getActiveSheet()->setTitle('Stock');

        $encabezados = ['Código', 'Artículo', 'N° de parte', 'Marca', 'Categoría', 'Ubicación', 'Unidad', 'Stock', 'Mínimo', 'Bajo mínimo'];

        if ($conCostos) {
            array_push($encabezados, 'Costo promedio', 'Valor');
        }

        $hoja->setCellValue('A1', 'Stock del almacén al '.RelojOperativo::fechaDeHoy()->format('d/m/Y'));
        $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $this->encabezados($hoja, 3, $encabezados);

        $fila = 4;

        foreach ($articulos as $articulo) {
            $valores = [
                $articulo->codigo,
                $articulo->nombre,
                $articulo->numero_parte,
                $articulo->marca,
                $articulo->categoria?->nombre,
                $articulo->ubicacion?->nombre,
                $articulo->unidad->abreviatura(),
                (float) $articulo->stock,
                $articulo->stock_minimo === null ? null : (float) $articulo->stock_minimo,
                $articulo->estaBajoMinimo() ? 'Sí' : '',
            ];

            if ($conCostos) {
                array_push($valores, (float) $articulo->costo_promedio, (float) $articulo->valor);
            }

            $hoja->fromArray($valores, null, "A{$fila}", true);
            $fila++;
        }

        $ultima = $fila - 1;
        $hoja->getStyle("H4:I{$fila}")->getNumberFormat()->setFormatCode(self::FORMATO_CANTIDAD);

        if ($conCostos) {
            $hoja->getStyle("K4:K{$fila}")->getNumberFormat()->setFormatCode(self::FORMATO_UNITARIO);
            $hoja->getStyle("L4:L{$fila}")->getNumberFormat()->setFormatCode(self::FORMATO_SOLES);
            $hoja->setCellValue("K{$fila}", 'Total');
            $hoja->setCellValue("L{$fila}", $ultima >= 4 ? "=SUBTOTAL(9,L4:L{$ultima})" : 0);
            $hoja->getStyle("K{$fila}:L{$fila}")->getFont()->setBold(true);
        }

        $this->anchos($hoja, count($encabezados));
        $hoja->freezePane('C4');
        $hoja->setAutoFilter('A3:'.$hoja->getHighestColumn().'3');

        (new Xlsx($libro))->save($ruta);
    }

    /**
     * Un bloque por artículo, en el orden del formato 13.1.
     *
     * @param  list<Articulo>  $articulos
     */
    public function kardex(array $articulos, string $desde, string $hasta, string $ruta): void
    {
        $libro = new Spreadsheet;
        $hoja = $libro->getActiveSheet()->setTitle('Kardex');
        $inicio = Carbon::parse($desde)->startOfDay();
        $fin = Carbon::parse($hasta)->startOfDay();

        $hoja->setCellValue('A1', 'REGISTRO DE INVENTARIO PERMANENTE VALORIZADO (FORMATO 13.1)');
        $hoja->setCellValue('A2', 'Período: del '.$inicio->format('d/m/Y').' al '.$fin->format('d/m/Y'));
        $hoja->setCellValue('A3', 'Método de valuación: promedio ponderado · Establecimiento: almacén de taller');
        $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(13);

        $fila = 5;

        foreach ($articulos as $articulo) {
            $hoja->setCellValue("A{$fila}", "{$articulo->codigo} · {$articulo->nombre}");
            $hoja->setCellValue("H{$fila}", 'Unidad: '.$articulo->unidad->label().' ('.$articulo->unidad->abreviatura().')');
            $hoja->getStyle("A{$fila}:P{$fila}")->getFont()->setBold(true);
            $hoja->getStyle("A{$fila}:P{$fila}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE5E7EB');
            $fila++;

            $hoja->mergeCells("F{$fila}:H{$fila}");
            $hoja->mergeCells("I{$fila}:K{$fila}");
            $hoja->mergeCells("L{$fila}:N{$fila}");
            $hoja->setCellValue("F{$fila}", 'ENTRADAS');
            $hoja->setCellValue("I{$fila}", 'SALIDAS');
            $hoja->setCellValue("L{$fila}", 'SALDO FINAL');
            $hoja->getStyle("F{$fila}:N{$fila}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $hoja->getStyle("F{$fila}:N{$fila}")->getFont()->setBold(true);
            $fila++;

            $this->encabezados($hoja, $fila, [
                'Fecha', 'Documento', 'Tipo de operación', 'Cód. SUNAT', 'Unidad / proveedor',
                'Cantidad', 'Costo unit.', 'Costo total',
                'Cantidad', 'Costo unit.', 'Costo total',
                'Cantidad', 'Costo unit.', 'Costo total',
            ]);
            $fila++;

            $anterior = MovimientoAlmacen::query()
                ->where('articulo_id', $articulo->id)
                ->whereDate('fecha', '<', $inicio->toDateString())
                ->orderByDesc('fecha')
                ->orderByDesc('id')
                ->first();

            $hoja->fromArray([
                $inicio->format('d/m/Y'), '', 'Saldo anterior', '', '',
                null, null, null, null, null, null,
                (float) ($anterior->saldo_cantidad ?? 0), (float) ($anterior->saldo_costo_unitario ?? 0), (float) ($anterior->saldo_valor ?? 0),
            ], null, "A{$fila}", true);
            $hoja->getStyle("A{$fila}:N{$fila}")->getFont()->setItalic(true);
            $primera = $fila;
            $fila++;

            $movimientos = MovimientoAlmacen::query()
                ->where('articulo_id', $articulo->id)
                ->whereDate('fecha', '>=', $inicio->toDateString())
                ->whereDate('fecha', '<=', $fin->toDateString())
                ->with(['vehiculo:id,placa', 'proveedor:id,razon_social,nombre_comercial'])
                ->orderBy('fecha')
                ->orderBy('id')
                ->get();

            $desdeFila = $fila;

            foreach ($movimientos as $movimiento) {
                $entra = $movimiento->tipo->esEntrada();

                $hoja->fromArray([
                    $movimiento->fecha->format('d/m/Y'),
                    $movimiento->documento,
                    $movimiento->tipo->label(),
                    $movimiento->tipo->codigoSunat(),
                    $movimiento->vehiculo_id !== null ? $movimiento->vehiculo->placa : $movimiento->proveedor?->nombre(),
                    $entra ? (float) $movimiento->entrada : null,
                    $entra ? (float) $movimiento->costo_unitario : null,
                    $entra ? (float) $movimiento->costo_total : null,
                    $entra ? null : (float) $movimiento->salida,
                    $entra ? null : (float) $movimiento->costo_unitario,
                    $entra ? null : (float) $movimiento->costo_total,
                    (float) $movimiento->saldo_cantidad,
                    (float) $movimiento->saldo_costo_unitario,
                    (float) $movimiento->saldo_valor,
                ], null, "A{$fila}", true);
                $hoja->getCell("D{$fila}")->setValueExplicit($movimiento->tipo->codigoSunat());
                $fila++;
            }

            $hasta = $fila - 1;
            $hoja->setCellValue("E{$fila}", 'Totales');
            foreach (['F', 'H', 'I', 'K'] as $columna) {
                $hoja->setCellValue("{$columna}{$fila}", $hasta >= $desdeFila ? "=SUM({$columna}{$desdeFila}:{$columna}{$hasta})" : 0);
            }
            $hoja->getStyle("E{$fila}:N{$fila}")->getFont()->setBold(true);

            foreach (['F', 'I', 'L'] as $columna) {
                $hoja->getStyle("{$columna}{$primera}:{$columna}{$fila}")->getNumberFormat()->setFormatCode(self::FORMATO_CANTIDAD);
            }
            foreach (['G', 'J', 'M'] as $columna) {
                $hoja->getStyle("{$columna}{$primera}:{$columna}{$fila}")->getNumberFormat()->setFormatCode(self::FORMATO_UNITARIO);
            }
            foreach (['H', 'K', 'N'] as $columna) {
                $hoja->getStyle("{$columna}{$primera}:{$columna}{$fila}")->getNumberFormat()->setFormatCode(self::FORMATO_SOLES);
            }

            $fila += 2;
        }

        $this->anchos($hoja, 14);
        $hoja->getColumnDimension('B')->setWidth(26);
        $hoja->getColumnDimension('C')->setWidth(24);

        (new Xlsx($libro))->save($ruta);
    }

    /**
     * @param  list<string>  $titulos
     */
    private function encabezados(Worksheet $hoja, int $fila, array $titulos): void
    {
        $hoja->fromArray($titulos, null, "A{$fila}");
        $rango = 'A'.$fila.':'.$hoja->getHighestColumn($fila).$fila;
        $estilo = $hoja->getStyle($rango);
        $estilo->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $estilo->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::COLOR_ENCABEZADO);
        $estilo->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
    }

    private function anchos(Worksheet $hoja, int $columnas): void
    {
        for ($i = 1; $i <= $columnas; $i++) {
            $hoja->getColumnDimensionByColumn($i)->setAutoSize(true);
        }

        $hoja->getStyle('A:A')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
    }
}
