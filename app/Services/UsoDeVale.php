<?php

namespace App\Services;

use App\Enums\EstadoValeCompra;
use App\Enums\Moneda;
use App\Enums\TipoValeCompra;
use App\Models\DetalleIngreso;
use App\Models\DetalleValeCompra;
use App\Models\ValeCompra;
use Illuminate\Support\Carbon;

/**
 * Si una compra puede entrar al almacén con un vale de compra.
 *
 * El comprobante tiene que ser lo que se aprobó: del mismo proveedor, en la
 * misma moneda, emitido mientras el vale valía, con artículos del vale y sin
 * pasarse de sus cantidades. Y por el mismo monto: si el comprobante dice
 * otra cosa, el vale se corrige y vuelve a aprobación. No hay tolerancia.
 *
 * Un vale se usa en una sola compra, salvo que gerencia le haya activado las
 * entregas parciales: entonces admite varias hasta completar su monto.
 */
class UsoDeVale
{
    public function __construct(private readonly FirmaDeVales $firma) {}

    /**
     * Desde cuándo toda compra necesita vale; null si no es obligatorio.
     */
    public static function obligatorioDesde(): ?Carbon
    {
        $desde = config('transpaty.compras.vale_obligatorio_desde');

        return blank($desde) ? null : Carbon::parse((string) $desde)->startOfDay();
    }

    /**
     * Un comprobante de esa fecha de emisión, ¿necesita vale?
     */
    public static function esObligatorio(string $fechaEmision): bool
    {
        $desde = self::obligatorioDesde();

        return $desde !== null && Carbon::parse($fechaEmision)->startOfDay()->gte($desde);
    }

    /**
     * Por qué la compra no puede entrar con ese vale; null si puede.
     *
     * @param  list<array{articulo_id: int, cantidad: float, bonificacion: bool}>  $lineas
     */
    public function error(ValeCompra $vale, int $proveedorId, Moneda $moneda, string $fechaEmision, float $total, array $lineas): ?string
    {
        $numero = $vale->numero();

        if ($vale->estaAnulado()) {
            return "El {$numero} está anulado: no sirve para comprar.";
        }

        if ($vale->estado !== EstadoValeCompra::Aprobado) {
            return "El {$numero} no está aprobado.";
        }

        if (! $this->firma->esAutentico($vale)) {
            return "El {$numero} no coincide con lo que se aprobó: fue modificado después de firmarse y no se puede usar.";
        }

        if ($vale->proveedor_id !== $proveedorId) {
            return "El {$numero} se aprobó para {$vale->proveedor->nombre()}, no para este proveedor.";
        }

        if ($vale->moneda !== $moneda) {
            return "El {$numero} se aprobó en {$vale->moneda->label()}: el comprobante tiene que estar en la misma moneda.";
        }

        return $this->errorDeFecha($vale, $fechaEmision)
            ?? $this->errorDeLineas($vale, $lineas)
            ?? $this->errorDeMonto($vale, $total);
    }

    private function errorDeFecha(ValeCompra $vale, string $fechaEmision): ?string
    {
        $emision = Carbon::parse($fechaEmision)->startOfDay();
        $zona = RelojOperativo::ahora()->getTimezone();

        // Una emergencia se compra mientras se espera la aprobación: vale
        // desde que se pidió. Una compra normal, desde que se aprobó.
        $desde = $vale->tipo === TipoValeCompra::Emergencia ? $vale->created_at : $vale->aprobado_at;
        $desde = Carbon::parse($desde?->copy()->setTimezone($zona)->toDateString());

        if ($emision->lt($desde)) {
            return "El comprobante es del {$emision->format('d/m/Y')}, antes de que el {$vale->numero()} valiera ({$desde->format('d/m/Y')}): la compra se hizo sin vale.";
        }

        if ($vale->vencido($emision)) {
            return "El {$vale->numero()} venció el {$vale->vence_el?->format('d/m/Y')} y el comprobante es del {$emision->format('d/m/Y')}. Pide un vale nuevo.";
        }

        return null;
    }

    /**
     * @param  list<array{articulo_id: int, cantidad: float, bonificacion: bool}>  $lineas
     */
    private function errorDeLineas(ValeCompra $vale, array $lineas): ?string
    {
        $autorizado = $vale->detalles->where('es_servicio', false)->keyBy('articulo_id');

        $yaEntro = DetalleIngreso::query()
            ->where('bonificacion', false)
            ->whereIn('ingreso_almacen_id', $vale->ingresos()->vigentes()->select('id'))
            ->groupBy('articulo_id')
            ->selectRaw('articulo_id, sum(cantidad) as cantidad')
            ->pluck('cantidad', 'articulo_id');

        $llega = [];

        foreach ($lineas as $linea) {
            // Lo que el proveedor regala no se aprobó ni se paga.
            if ($linea['bonificacion']) {
                continue;
            }

            if (! $autorizado->has($linea['articulo_id'])) {
                return "El {$vale->numero()} no autoriza uno de los artículos del comprobante. Si se compró algo distinto, corrige el vale para que vuelva a aprobación.";
            }

            $llega[$linea['articulo_id']] = ($llega[$linea['articulo_id']] ?? 0.0) + $linea['cantidad'];
        }

        foreach ($llega as $articuloId => $cantidad) {
            /** @var DetalleValeCompra $detalle */
            $detalle = $autorizado->get($articuloId);

            if ((float) ($yaEntro[$articuloId] ?? 0) + $cantidad - (float) $detalle->cantidad > 0.0005) {
                return "El {$vale->numero()} autoriza {$this->cifra((float) $detalle->cantidad)} de {$detalle->descripcion} y con este comprobante entrarían {$this->cifra((float) ($yaEntro[$articuloId] ?? 0) + $cantidad)}.";
            }
        }

        return null;
    }

    private function errorDeMonto(ValeCompra $vale, float $total): ?string
    {
        $autorizado = $vale->totalArticulos();
        $yaEntro = $vale->recibidoEnArticulos();
        $simbolo = $vale->moneda->simbolo();

        if ($vale->permite_parciales) {
            return $yaEntro + $total - $autorizado > ValeCompra::CENTIMO
                ? sprintf('Al %s le quedan %s %s por comprar y el comprobante es por %s %s. Corrige el vale para que vuelva a aprobación.', $vale->numero(), $simbolo, number_format($autorizado - $yaEntro, 2), $simbolo, number_format($total, 2))
                : null;
        }

        if ($yaEntro > 0) {
            return "El {$vale->numero()} ya se usó en otra compra: un vale sirve para una sola.";
        }

        return abs($total - $autorizado) > ValeCompra::CENTIMO
            ? sprintf('El comprobante es por %1$s %2$s y el %3$s se aprobó por %1$s %4$s. Si el precio cambió, corrige el vale: vuelve a aprobación.', $simbolo, number_format($total, 2), $vale->numero(), number_format($autorizado, 2))
            : null;
    }

    private function cifra(float $cantidad): string
    {
        return rtrim(rtrim(number_format($cantidad, 3, '.', ','), '0'), '.');
    }
}
