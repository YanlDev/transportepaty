<?php

namespace App\Services;

use App\Models\Factura;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Registra una factura a partir de su PDF y la asocia con las GR que cita.
 *
 * Si la factura ya existe (se cargó a mano, o se vuelve a subir), el PDF
 * manda: se actualizan sus cifras con las impresas y se le adjunta el
 * archivo, sin tocar lo que no sale del PDF —las fechas de cobro, la cuenta,
 * la observación de la cobranza— ni sacarle las GR que ya tenía.
 *
 * @phpstan-type ResultadoImportacion array{
 *     archivo: string,
 *     reconocida: bool,
 *     numero: string|null,
 *     nueva: bool,
 *     asociadas: list<string>,
 *     alertas: list<string>,
 * }
 */
class ImportadorFactura
{
    public function __construct(
        private readonly LectorFactura $lector,
        private readonly AsociadorFacturas $asociador,
    ) {}

    /**
     * @return ResultadoImportacion
     */
    public function importar(UploadedFile $archivo): array
    {
        $nombre = $archivo->getClientOriginalName();

        try {
            $campos = $this->lector->extraerDesdeArchivo((string) $archivo->getRealPath());
        } catch (\Throwable) {
            $campos = null;
        }

        if ($campos === null) {
            return $this->noReconocida($nombre, 'No se reconoció como una factura electrónica.');
        }

        if ($campos['emisor_ruc'] !== config('transpaty.facturacion.ruc_empresa')) {
            return $this->noReconocida($nombre, "La factura {$campos['numero']} la emitió el RUC {$campos['emisor_ruc']}, no la empresa.");
        }

        if ($campos['valor'] === null || $campos['total'] === null) {
            return $this->noReconocida($nombre, "No se pudieron leer los montos de la factura {$campos['numero']}.");
        }

        return DB::transaction(function () use ($archivo, $campos, $nombre): array {
            $factura = Factura::query()->firstOrNew(['numero' => $campos['numero']]);
            $nueva = ! $factura->exists;

            $factura->fill([
                'fecha_emision' => $campos['fecha_emision'],
                'fecha_vencimiento' => $campos['fecha_vencimiento'],
                'moneda' => $campos['moneda'],
                'cliente_ruc' => $campos['cliente_ruc'],
                'cliente_razon_social' => $campos['cliente_razon_social'],
                'gr_citadas' => $campos['gr_transportista'],
                'periodo_desde' => $campos['periodo_desde'],
                'periodo_hasta' => $campos['periodo_hasta'],
            ]);

            $factura->guardarCifrasImpresas([
                'monto' => $campos['valor'],
                'igv' => $campos['igv'],
                'total' => $campos['total'],
                'detraccion' => $campos['detraccion'],
                'neto' => $campos['neto'],
            ]);

            if ($nueva) {
                // La observación de la factura solo al crearla: en una que ya
                // existía es la nota de la cobranza y no se pisa.
                $factura->observacion = $campos['observacion'] === null ? null : mb_substr($campos['observacion'], 0, 1000);
            }

            $factura->save();

            // El origen no se borra: es el archivo subido por HTTP.
            $factura->addMedia($archivo)->preservingOriginal()->toMediaCollection('archivo');

            $resultado = $this->asociador->asociarCitadas($factura);

            return [
                'archivo' => $nombre,
                'reconocida' => true,
                'numero' => $factura->numero,
                'nueva' => $nueva,
                'asociadas' => $resultado['asociadas'],
                'alertas' => $resultado['alertas'],
            ];
        });
    }

    /**
     * @return ResultadoImportacion
     */
    private function noReconocida(string $nombre, string $motivo): array
    {
        return [
            'archivo' => $nombre,
            'reconocida' => false,
            'numero' => null,
            'nueva' => false,
            'asociadas' => [],
            'alertas' => [$motivo],
        ];
    }
}
