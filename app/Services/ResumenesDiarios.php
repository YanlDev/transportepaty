<?php

namespace App\Services;

use App\Enums\TipoAlerta;
use App\Models\ConductorDocumento;
use App\Models\Factura;
use App\Models\VehiculoDocumento;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * El contenido de las alertas que salen una vez al día. Cada resumen dice
 * cuántos son y lista los primeros; si no hay nada que contar devuelve null
 * y ese día no se manda nada.
 *
 * @phpstan-type Resumen array{titulo: string, mensaje: string, url: string}
 */
class ResumenesDiarios
{
    /** Cuántas líneas lleva un resumen; el resto se cuenta al final. */
    private const LINEAS = 10;

    /**
     * @return Resumen|null
     */
    public function de(TipoAlerta $tipo): ?array
    {
        return match ($tipo) {
            TipoAlerta::DocumentosPorVencer => $this->documentos(),
            TipoAlerta::FacturasVencidas => $this->facturas(),
            default => null,
        };
    }

    /**
     * Los papeles de las unidades y de los conductores activos que ya
     * vencieron o vencen dentro del plazo de aviso, los más urgentes primero.
     *
     * @return Resumen|null
     */
    private function documentos(): ?array
    {
        $hoy = RelojOperativo::fechaDeHoy();
        $hasta = $hoy->addDays(VehiculoDocumento::DIAS_AVISO_VENCIMIENTO)->toDateString();

        $deUnidades = VehiculoDocumento::query()
            ->with('vehiculo:id,placa')
            ->whereHas('vehiculo')
            ->whereNotNull('fecha_vencimiento')
            ->whereDate('fecha_vencimiento', '<=', $hasta)
            ->get()
            ->map(fn (VehiculoDocumento $documento): array => [
                'fecha' => CarbonImmutable::parse($documento->fecha_vencimiento),
                'quien' => $documento->vehiculo->placa,
                'que' => $documento->nombre ?? $documento->tipo->label(),
            ]);

        $deConductores = ConductorDocumento::query()
            ->with('conductor:id,nombres,apellidos')
            ->whereHas('conductor', fn ($query) => $query->where('activo', true))
            ->whereNotNull('fecha_vencimiento')
            ->whereDate('fecha_vencimiento', '<=', $hasta)
            ->get()
            ->map(fn (ConductorDocumento $documento): array => [
                'fecha' => CarbonImmutable::parse($documento->fecha_vencimiento),
                'quien' => "{$documento->conductor->nombres} {$documento->conductor->apellidos}",
                'que' => $documento->tipo->label(),
            ]);

        $documentos = $deUnidades->concat($deConductores)->sortBy(fn (array $documento): string => $documento['fecha']->toDateString())->values();

        if ($documentos->isEmpty()) {
            return null;
        }

        $vencidos = $documentos->filter(fn (array $documento): bool => $documento['fecha']->lt($hoy))->count();
        $porVencer = $documentos->count() - $vencidos;

        return [
            'titulo' => implode(' y ', array_filter([
                $vencidos > 0 ? $this->cuenta($vencidos, 'documento vencido', 'documentos vencidos') : null,
                $porVencer > 0 ? $this->cuenta($porVencer, 'por vencer', 'por vencer') : null,
            ])),
            'mensaje' => $this->lineas($documentos, fn (array $documento): string => sprintf(
                '%s · %s · %s %s',
                $documento['quien'],
                $documento['que'],
                $documento['fecha']->lt($hoy) ? 'venció el' : 'vence el',
                $documento['fecha']->format('d/m'),
            )),
            'url' => route('dashboard'),
        ];
    }

    /**
     * Las facturas pasadas de su vencimiento a las que todavía les falta
     * cobrar algo, las más atrasadas primero.
     *
     * @return Resumen|null
     */
    private function facturas(): ?array
    {
        $vencidas = Factura::query()
            ->where(fn ($query) => $query
                ->whereNull('fecha_pago')
                ->orWhere(fn ($query) => $query->where('detraccion', '>', 0)->whereNull('fecha_detraccion')))
            ->get()
            ->filter(fn (Factura $factura): bool => ($factura->diasVencida() ?? 0) > 0)
            ->sortByDesc(fn (Factura $factura): int => (int) $factura->diasVencida())
            ->values();

        if ($vencidas->isEmpty()) {
            return null;
        }

        return [
            'titulo' => $this->cuenta($vencidas->count(), 'factura vencida', 'facturas vencidas').' sin cobrar',
            'mensaje' => $this->lineas($vencidas, fn (Factura $factura): string => sprintf(
                '%s · %s · %s %s · %d días',
                $factura->numero,
                $factura->cliente_razon_social ?? 'Sin cliente',
                $factura->moneda->simbolo(),
                number_format($this->pendiente($factura), 2),
                $factura->diasVencida(),
            )),
            'url' => route('contabilidad.index', ['estado' => 'facturado']),
        ];
    }

    /** Lo que falta cobrar: el neto si no entró, más la detracción si falta. */
    private function pendiente(Factura $factura): float
    {
        return ($factura->fecha_pago === null ? (float) $factura->neto : 0.0)
            + ($factura->faltaDetraccion() ? (float) $factura->detraccion : 0.0);
    }

    /**
     * @template T
     *
     * @param  Collection<int, T>  $elementos
     * @param  callable(T): string  $linea
     */
    private function lineas(Collection $elementos, callable $linea): string
    {
        $lineas = $elementos->take(self::LINEAS)->map($linea);
        $resto = $elementos->count() - self::LINEAS;

        if ($resto > 0) {
            $lineas->push("…y {$resto} más.");
        }

        return $lineas->implode("\n");
    }

    private function cuenta(int $cantidad, string $singular, string $plural): string
    {
        return $cantidad.' '.($cantidad === 1 ? $singular : $plural);
    }
}
