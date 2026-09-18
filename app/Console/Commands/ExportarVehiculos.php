<?php

namespace App\Console\Commands;

use App\Enums\TipoCaja;
use App\Enums\TipoDocumento;
use App\Enums\TipoVehiculo;
use App\Models\Vehiculo;
use Carbon\CarbonInterface;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * La ficha de cada vehículo en un .xlsx: identidad (marca, modelo, VIN, motor)
 * y, al lado, el vencimiento de cada documento obligatorio en su propia
 * columna, para poder ordenar la hoja por el papel que está por caer.
 *
 * Los filtros `--caja` y `--tipo` existen porque la flota se revisa por partes:
 * los tractos automáticos son un grupo que se negocia y se mantiene aparte de
 * los mecánicos.
 */
#[Signature('transpaty:exportar-vehiculos
    {--caja= : Solo los de esta caja: automatica o mecanica (deja fuera las carretas, que no llevan)}
    {--tipo= : Solo los de este tipo: tracto o carreta}
    {--salida= : Ruta del .xlsx a escribir (por defecto, la carpeta actual)}')]
#[Description('Exporta a Excel la ficha de los vehículos con el vencimiento de cada documento.')]
class ExportarVehiculos extends Command
{
    /**
     * Las columnas fijas, antes de las de vencimiento. Estas últimas se arman
     * en `encabezados()` a partir de los tipos de documento, para que agregar
     * un obligatorio nuevo no deje la hoja desalineada.
     *
     * @var array<int, string>
     */
    private const IDENTIDAD = [
        'Placa',
        'Tipo',
        'Marca',
        'Modelo',
        'Año',
        'Caja',
        'Color',
        'Ejes',
        'VIN',
        'N° motor',
        'Estado',
        'Peso neto (kg)',
        'Peso bruto (kg)',
        'Carga útil (kg)',
        'Fecha de adquisición',
    ];

    /**
     * Los documentos que llevan fecha y por lo tanto merecen una columna de
     * vencimiento. La tarjeta de propiedad no caduca, así que va aparte.
     *
     * @var array<int, TipoDocumento>
     */
    private const CON_VENCIMIENTO = [
        TipoDocumento::Soat,
        TipoDocumento::RevisionTecnicaCarga,
        TipoDocumento::HabilitacionMtc,
        TipoDocumento::Matpel,
    ];

    private const FILA_ENCABEZADO = 1;

    public function handle(): int
    {
        $caja = $this->option('caja') === null ? null : TipoCaja::tryFrom((string) $this->option('caja'));
        $tipo = $this->option('tipo') === null ? null : TipoVehiculo::tryFrom((string) $this->option('tipo'));

        if ($this->option('caja') !== null && $caja === null) {
            $this->error('La caja debe ser "automatica" o "mecanica".');

            return self::FAILURE;
        }

        if ($this->option('tipo') !== null && $tipo === null) {
            $this->error('El tipo debe ser "tracto" o "carreta".');

            return self::FAILURE;
        }

        $vehiculos = Vehiculo::query()
            ->with('documentos')
            ->when($caja !== null, fn ($consulta) => $consulta->where('caja', $caja))
            ->when($tipo !== null, fn ($consulta) => $consulta->where('tipo', $tipo))
            ->orderBy('placa')
            ->get();

        if ($vehiculos->isEmpty()) {
            $this->warn('Ningún vehículo cumple ese filtro.');

            return self::SUCCESS;
        }

        $ruta = $this->rutaDeSalida($caja, $tipo);

        $libro = new Spreadsheet;
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Vehículos');

        $encabezados = $this->encabezados();

        foreach ($encabezados as $indice => $encabezado) {
            $hoja->setCellValue([$indice + 1, self::FILA_ENCABEZADO], $encabezado);
        }

        $fila = self::FILA_ENCABEZADO;

        foreach ($vehiculos as $vehiculo) {
            $fila++;

            foreach ($this->celdas($vehiculo) as $columna => $valor) {
                if ($valor === null) {
                    continue;
                }

                $hoja->setCellValue([$columna + 1, $fila], $valor);
            }
        }

        $this->darFormato($hoja, count($encabezados), $fila);

        (new Xlsx($libro))->save($ruta);
        $libro->disconnectWorksheets();

        $this->info("{$vehiculos->count()} vehículos exportados.");
        $this->line("Archivo: {$ruta}");

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function encabezados(): array
    {
        $vencimientos = array_map(
            fn (TipoDocumento $tipo): string => 'Vence '.$tipo->label(),
            self::CON_VENCIMIENTO,
        );

        return [
            ...self::IDENTIDAD,
            'Tarjeta de propiedad',
            ...$vencimientos,
            'Situación',
        ];
    }

    /**
     * Los valores de una fila, en el orden de `encabezados()`.
     *
     * Las fechas van como número de Excel y no como texto: el archivo se abre
     * para ordenar por vencimiento, y un texto no se ordena cronológicamente.
     *
     * @return array<int, string|float|int|null>
     */
    private function celdas(Vehiculo $vehiculo): array
    {
        $vencimientos = array_map(
            fn (TipoDocumento $tipo): ?float => $this->vencimiento($vehiculo, $tipo),
            self::CON_VENCIMIENTO,
        );

        return [
            $vehiculo->placa,
            $vehiculo->tipo->label(),
            $vehiculo->marca,
            $vehiculo->modelo,
            $vehiculo->anio,
            $vehiculo->caja?->label(),
            $vehiculo->color,
            $vehiculo->ejes,
            $vehiculo->vin,
            $vehiculo->numero_motor,
            $vehiculo->estado->label(),
            $vehiculo->peso_neto === null ? null : (float) $vehiculo->peso_neto,
            $vehiculo->peso_bruto === null ? null : (float) $vehiculo->peso_bruto,
            $vehiculo->carga_util === null ? null : (float) $vehiculo->carga_util,
            $vehiculo->fecha_adquisicion === null ? null : $this->fecha($vehiculo->fecha_adquisicion),
            $vehiculo->documentoDe(TipoDocumento::TarjetaPropiedad) === null ? 'Falta' : 'Cargada',
            ...$vencimientos,
            $this->situacion($vehiculo),
        ];
    }

    /**
     * La fecha de vencimiento del documento, o null si el vehículo no lo tiene
     * cargado o el documento no aplica a su tipo.
     */
    private function vencimiento(Vehiculo $vehiculo, TipoDocumento $tipo): ?float
    {
        $vence = $vehiculo->documentoDe($tipo)?->fecha_vencimiento;

        return $vence === null ? null : $this->fecha($vence);
    }

    /**
     * El semáforo documental en palabras: lo mismo que la columna «problemas»
     * del listado, para poder filtrar la hoja por lo que hay que atender.
     */
    private function situacion(Vehiculo $vehiculo): string
    {
        $estado = $vehiculo->estadoDocumental();

        $partes = [];

        if ($estado['vencidos'] !== []) {
            $partes[] = 'vencido: '.implode(', ', $estado['vencidos']);
        }

        if ($estado['faltantes'] !== []) {
            $partes[] = 'falta: '.implode(', ', $estado['faltantes']);
        }

        if ($estado['por_vencer'] !== []) {
            $partes[] = 'por vencer: '.implode(', ', $estado['por_vencer']);
        }

        return $partes === [] ? 'Al día' : ucfirst(implode(' · ', $partes));
    }

    /**
     * El serial de fecha que entiende Excel, contado sobre la fecha pelada.
     * Igual que en `ExportadorCobranza`: pasar por `Date::PHPToExcel()` mete
     * la zona horaria del proceso y con el huso de Lima devuelve el día
     * anterior.
     */
    private function fecha(CarbonInterface $fecha): float
    {
        return (float) Carbon::parse('1899-12-30')
            ->diffInDays($fecha->copy()->startOfDay(), absolute: true);
    }

    private function rutaDeSalida(?TipoCaja $caja, ?TipoVehiculo $tipo): string
    {
        $salida = $this->option('salida');

        if ($salida !== null && $salida !== '') {
            return (string) $salida;
        }

        $partes = array_filter(['vehiculos', $tipo?->value, $caja?->value]);

        return getcwd().DIRECTORY_SEPARATOR.implode('-', $partes).'-'.Carbon::now()->format('Y-m-d-Hi').'.xlsx';
    }

    private function darFormato(Worksheet $hoja, int $ultimaColumna, int $ultimaFila): void
    {
        $hoja->getStyle([1, self::FILA_ENCABEZADO, $ultimaColumna, self::FILA_ENCABEZADO])
            ->getFont()->setBold(true);

        $hoja->getStyle([1, self::FILA_ENCABEZADO, $ultimaColumna, self::FILA_ENCABEZADO])
            ->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('EFF2F7');

        $hoja->freezePane('B2');

        $ultimaLetra = Coordinate::stringFromColumnIndex($ultimaColumna);
        $hoja->setAutoFilter('A'.self::FILA_ENCABEZADO.':'.$ultimaLetra.max($ultimaFila, self::FILA_ENCABEZADO));

        foreach (range(1, $ultimaColumna) as $columna) {
            $hoja->getColumnDimensionByColumn($columna)->setAutoSize(true);
        }

        if ($ultimaFila <= self::FILA_ENCABEZADO) {
            return;
        }

        $primeraFila = self::FILA_ENCABEZADO + 1;
        $columnaAdquisicion = count(self::IDENTIDAD);
        $primerVencimiento = $columnaAdquisicion + 2;

        $this->formatear($hoja, $columnaAdquisicion, $primeraFila, $ultimaFila, NumberFormat::FORMAT_DATE_DDMMYYYY);

        foreach (range($primerVencimiento, $primerVencimiento + count(self::CON_VENCIMIENTO) - 1) as $columna) {
            $this->formatear($hoja, $columna, $primeraFila, $ultimaFila, NumberFormat::FORMAT_DATE_DDMMYYYY);
        }

        foreach ([12, 13, 14] as $columna) {
            $this->formatear($hoja, $columna, $primeraFila, $ultimaFila, '#,##0');
        }
    }

    private function formatear(Worksheet $hoja, int $columna, int $desde, int $hasta, string $formato): void
    {
        $hoja->getStyle([$columna, $desde, $columna, $hasta])
            ->getNumberFormat()->setFormatCode($formato);
    }
}
